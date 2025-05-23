<?php

/**
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2017-2018 Tobias Reich
 * Copyright (c) 2018-2025 LycheeOrg.
 */

namespace App\Actions\Photo;

use App\Enum\SizeVariantType;
use App\Exceptions\Internal\LycheeAssertionError;
use App\Exceptions\Internal\QueryBuilderException;
use App\Exceptions\ModelDBException;
use App\Image\FileDeleter;
use App\Models\Album;
use App\Models\Photo;
use App\Models\SizeVariant;
use App\Models\Statistics;
use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Database\Query\JoinClause;

/**
 * Deletes the photos with the designated IDs **efficiently**.
 *
 * This class deliberately violates the principle of separations of concerns.
 * In an ideal world, the method would simply call `->delete()` on every
 * `Photo` model and the `Photo` model would take care of deleting its
 * associated size variants including the media files.
 * But this is extremely inefficient due to Laravel's architecture:
 *
 *  - Models are heavyweight god classes such that every instance also carries
 *    the whole code for serialization/deserialization
 *  - Models are active records (and don't use the unit-of-work pattern), i.e.
 *    every deletion of a model directly triggers a DB operation; they are
 *    not deferred into a batch operation
 *
 * Moreover, while removing the records for photos and size variants from the
 * DB can be implemented rather efficiently, the actual file operations may
 * take some time.
 * Especially, if the files are not stored locally but on a remote file system.
 * Hence, this method collects all files which need to be removed.
 * The caller can then decide to delete them asynchronously.
 */
readonly class Delete
{
	protected FileDeleter $fileDeleter;

	public function __construct()
	{
		$this->fileDeleter = new FileDeleter();
	}

	/**
	 * Deletes the designated photos from the DB.
	 *
	 * The method only deletes the records for photos, their size variants.
	 * The method does not delete the associated files from physical storage.
	 * Instead, the method returns an object in which all these files have
	 * been collected.
	 * This object can (and must) be used to eventually delete the files,
	 * however doing so can be deferred.
	 *
	 * The method allows deleting individual photos designated by
	 * `$photoIDs` or photos of entire albums designated by `$albumIDs`.
	 * The latter is more efficient, if albums shall be deleted, because
	 * it results in more succinct SQL queries.
	 * Both parameters can be used simultaneously and result in a merged
	 * deletion of the joined set of photos.
	 *
	 * @param string[] $photo_ids the photo IDs
	 * @param string[] $album_ids the album IDs
	 *
	 * @return FileDeleter contains the collected files which became obsolete
	 *
	 * @throws ModelDBException
	 */
	public function do(array $photo_ids, array $album_ids = []): FileDeleter
	{
		// TODO: replace this with pipelines, This is typically the kind of pattern.
		try {
			$this->collectSizeVariantPathsByPhotoID($photo_ids);
			$this->collectSizeVariantPathsByAlbumID($album_ids);
			$this->collectLivePhotoPathsByPhotoID($photo_ids);
			$this->collectLivePhotoPathsByAlbumID($album_ids);
			$this->deleteDBRecords($photo_ids, $album_ids);
			// @codeCoverageIgnoreStart
		} catch (QueryBuilderException $e) {
			throw ModelDBException::create('photos', 'deleting', $e);
		}
		// @codeCoverageIgnoreEnd
		Album::query()->whereIn('header_id', $photo_ids)->update(['header_id' => null]);

		return $this->fileDeleter;
	}

	/**
	 * Collects all short paths of size variants which shall be deleted from
	 * disk.
	 *
	 * Size variants which belong to a photo which has a duplicate that is
	 * not going to be deleted are skipped.
	 *
	 * @param array<int,string> $photo_ids the photo IDs
	 *
	 * @return void
	 *
	 * @throws QueryBuilderException
	 */
	private function collectSizeVariantPathsByPhotoID(array $photo_ids): void
	{
		try {
			if (count($photo_ids) === 0) {
				return;
			}

			// Maybe consider doing multiple queries for the different storage types.
			$size_variants = SizeVariant::query()
				->from('size_variants as sv')
				->select(['sv.short_path', 'sv.storage_disk'])
				->join('photos as p', 'p.id', '=', 'sv.photo_id')
				->leftJoin('photos as dup', function (JoinClause $join) use ($photo_ids): void {
					$join
						->on('dup.checksum', '=', 'p.checksum')
						->whereNotIn('dup.id', $photo_ids);
				})
				->whereIn('p.id', $photo_ids)
				->whereNull('dup.id')
				->get();
			$this->fileDeleter->addSizeVariants($size_variants);
			// @codeCoverageIgnoreStart
		} catch (\InvalidArgumentException $e) {
			throw LycheeAssertionError::createFromUnexpectedException($e);
		}
		// @codeCoverageIgnoreEnd
	}

	/**
	 * Collects all short paths of size variants which shall be deleted from
	 * disk.
	 *
	 * Size variants which belong to a photo which has a duplicate that is
	 * not going to be deleted are skipped.
	 *
	 * @param array<int,string> $album_ids the album IDs
	 *
	 * @return void
	 *
	 * @throws QueryBuilderException
	 */
	private function collectSizeVariantPathsByAlbumID(array $album_ids): void
	{
		try {
			if (count($album_ids) === 0) {
				return;
			}

			// Maybe consider doing multiple queries for the different storage types.
			$size_variants = SizeVariant::query()
				->from('size_variants as sv')
				->select(['sv.short_path', 'sv.storage_disk'])
				->join('photos as p', 'p.id', '=', 'sv.photo_id')
				->leftJoin('photos as dup', function (JoinClause $join) use ($album_ids): void {
					$join
						->on('dup.checksum', '=', 'p.checksum')
						->whereNotIn('dup.album_id', $album_ids);
				})
				->whereIn('p.album_id', $album_ids)
				->whereNull('dup.id')
				->get();
			$this->fileDeleter->addSizeVariants($size_variants);
			// @codeCoverageIgnoreStart
		} catch (\InvalidArgumentException $e) {
			throw LycheeAssertionError::createFromUnexpectedException($e);
		}
		// @codeCoverageIgnoreEnd
	}

	/**
	 * Collects all short paths of live photos which shall be deleted from
	 * disk.
	 *
	 * Live photos which have a duplicate that is not going to be deleted are
	 * skipped.
	 *
	 * @param array<int,string> $photo_ids the photo IDs
	 *
	 * @return void
	 *
	 * @throws QueryBuilderException
	 */
	private function collectLivePhotoPathsByPhotoID(array $photo_ids)
	{
		try {
			if (count($photo_ids) === 0) {
				return;
			}

			$live_photo_short_paths = Photo::query()
				->from('photos as p')
				->select(['p.live_photo_short_path', 'sv.storage_disk'])
				->join('size_variants as sv', function (JoinClause $join): void {
					$join
						->on('sv.photo_id', '=', 'p.id')
						->where('sv.type', '=', SizeVariantType::ORIGINAL);
				})
				->leftJoin('photos as dup', function (JoinClause $join) use ($photo_ids): void {
					$join
						->on('dup.live_photo_checksum', '=', 'p.live_photo_checksum')
						->whereNotIn('dup.id', $photo_ids);
				})
				->whereIn('p.id', $photo_ids)
				->whereNull('dup.id')
				->whereNotNull('p.live_photo_short_path')
				->get(['p.live_photo_short_path', 'sv.storage_disk']);

			$live_variants_short_paths_grouped = $live_photo_short_paths->groupBy('storage_disk');
			$live_variants_short_paths_grouped->each(
				fn ($live_variants_short_paths, $disk) => $this->fileDeleter->addFiles($live_variants_short_paths->map(fn ($lv) => $lv->live_photo_short_path), $disk)
			);
			// @codeCoverageIgnoreStart
		} catch (\InvalidArgumentException $e) {
			throw LycheeAssertionError::createFromUnexpectedException($e);
		}
		// @codeCoverageIgnoreEnd
	}

	/**
	 * Collects all short paths of live photos which shall be deleted from
	 * disk.
	 *
	 * Live photos which have a duplicate that is not going to be deleted are
	 * skipped.
	 *
	 * @param array<int,string> $album_ids the album IDs
	 *
	 * @return void
	 *
	 * @throws QueryBuilderException
	 */
	private function collectLivePhotoPathsByAlbumID(array $album_ids)
	{
		try {
			if (count($album_ids) === 0) {
				return;
			}

			$live_photo_short_paths = Photo::query()
				->from('photos as p')
				->select(['p.live_photo_short_path', 'sv.storage_disk'])
				->join('size_variants as sv', function (JoinClause $join): void {
					$join
						->on('sv.photo_id', '=', 'p.id')
						->where('sv.type', '=', SizeVariantType::ORIGINAL);
				})
				->leftJoin('photos as dup', function (JoinClause $join) use ($album_ids): void {
					$join
						->on('dup.live_photo_checksum', '=', 'p.live_photo_checksum')
						->whereNotIn('dup.album_id', $album_ids);
				})
				->whereIn('p.album_id', $album_ids)
				->whereNull('dup.id')
				->whereNotNull('p.live_photo_short_path')
				->get(['p.live_photo_short_path', 'sv.storage_disk']);

			$live_variants_short_paths_grouped = $live_photo_short_paths->groupBy('storage_disk');
			$live_variants_short_paths_grouped->each(
				fn ($live_variants_short_paths, $disk) => $this->fileDeleter->addFiles($live_variants_short_paths->map(fn ($lv) => $lv->live_photo_short_path), $disk)
			);
			// @codeCoverageIgnoreStart
		} catch (\InvalidArgumentException $e) {
			throw LycheeAssertionError::createFromUnexpectedException($e);
		}
		// @codeCoverageIgnoreEnd
	}

	/**
	 * Deletes the records from DB.
	 *
	 * The records are deleted in such an order that foreign keys are not
	 * broken.
	 *
	 * @param array<int,string> $photo_ids the photo IDs
	 * @param array<int,string> $album_ids the album IDs
	 *
	 * @return void
	 *
	 * @throws QueryBuilderException
	 */
	private function deleteDBRecords(array $photo_ids, array $album_ids): void
	{
		try {
			if (count($photo_ids) !== 0) {
				SizeVariant::query()
					->whereIn('size_variants.photo_id', $photo_ids)
					->delete();
			}
			if (count($album_ids) !== 0) {
				SizeVariant::query()
					->whereExists(function (BaseBuilder $query) use ($album_ids): void {
						$query
							->from('photos', 'p')
							->whereColumn('p.id', '=', 'size_variants.photo_id')
							->whereIn('p.album_id', $album_ids);
					})
					->delete();
			}
			if (count($photo_ids) !== 0) {
				Statistics::query()
					->whereIn('photo_id', $photo_ids)
					->delete();
			}
			if (count($album_ids) !== 0) {
				Statistics::query()
					->whereExists(function (BaseBuilder $query) use ($album_ids): void {
						$query
							->from('photos', 'p')
							->whereColumn('p.id', '=', 'statistics.photo_id')
							->whereIn('p.album_id', $album_ids);
					})
					->delete();
			}
			if (count($photo_ids) !== 0) {
				Photo::query()->whereIn('id', $photo_ids)->delete();
			}
			if (count($album_ids) !== 0) {
				Photo::query()->whereIn('album_id', $album_ids)->delete();
			}
			// @codeCoverageIgnoreStart
		} catch (\InvalidArgumentException $e) {
			throw LycheeAssertionError::createFromUnexpectedException($e);
		}
		// @codeCoverageIgnoreEnd
	}
}