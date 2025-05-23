<?php

/**
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2017-2018 Tobias Reich
 * Copyright (c) 2018-2025 LycheeOrg.
 */

namespace App\Actions\SizeVariant;

use App\Exceptions\Internal\LycheeAssertionError;
use App\Exceptions\Internal\QueryBuilderException;
use App\Exceptions\ModelDBException;
use App\Image\FileDeleter;
use App\Models\SizeVariant;
use Illuminate\Database\Query\JoinClause;

/**
 * Deletes the size variants with the designated IDs **efficiently**.
 *
 * This class deliberately violates the principle of separations of concerns.
 * In an ideal world, the method would simply call `->delete()` on every
 * `SizeVariant` model and the `SizeVariant` model would take care of deleting
 * its associated media files.
 * But this is extremely inefficient due to Laravel's architecture:
 *
 *  - Models are heavyweight god classes such that every instance also carries
 *    the whole code for serialization/deserialization
 *  - Models are active records (and don't use the unit-of-work pattern), i.e.
 *    every deletion of a model directly triggers a DB operation; they are
 *    not deferred into a batch operation
 *
 * Moreover, while removing the records for size variants from the DB can be
 * implemented rather efficiently, the actual file operations may take some
 * time.
 * Especially, if the files are not stored locally but on a remote file system.
 * Hence, this method collects all files which need to be removed.
 * The caller can then decide to delete them asynchronously.
 */
class Delete
{
	/**
	 * Deletes the designated size variants from the DB.
	 *
	 * The method only deletes the records for size variants.
	 * The method does not delete the associated files from the physical storage.
	 * Instead, the method returns an object in which all these files have been collected.
	 * This object can (and must) be used to eventually delete the files,
	 * however doing so can be deferred.
	 *
	 * @param int[] $sv_ids the size variant IDs
	 *
	 * @return FileDeleter contains the collected files which became obsolete
	 *
	 * @throws ModelDBException
	 */
	public function do(array $sv_ids): FileDeleter
	{
		try {
			$file_deleter = new FileDeleter();

			// Get all short paths of size variants which are going to be deleted.
			// But exclude those short paths which are duplicated by a size
			// variant which is not going to be deleted.
			$size_variants = SizeVariant::query()
				->from('size_variants as sv')
				->select(['sv.short_path', 'sv.storage_disk'])
				->leftJoin('size_variants as dup', function (JoinClause $join) use ($sv_ids): void {
					$join
						->on('dup.short_path', '=', 'sv.short_path')
						->whereNotIn('dup.id', $sv_ids);
				})
				->whereIn('sv.id', $sv_ids)
				->whereNull('dup.id')
				->get();
			$file_deleter->addSizeVariants($size_variants);

			SizeVariant::query()
				->whereIn('id', $sv_ids)
				->delete();

			return $file_deleter;
			// @codeCoverageIgnoreStart
		} catch (QueryBuilderException $e) {
			throw ModelDBException::create('size variants', 'deleting', $e);
		} catch (\InvalidArgumentException $e) {
			throw LycheeAssertionError::createFromUnexpectedException($e);
		}
		// @codeCoverageIgnoreEnd
	}
}