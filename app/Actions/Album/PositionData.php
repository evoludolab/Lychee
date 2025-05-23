<?php

/**
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2017-2018 Tobias Reich
 * Copyright (c) 2018-2025 LycheeOrg.
 */

namespace App\Actions\Album;

use App\Contracts\Models\AbstractAlbum;
use App\Enum\SizeVariantType;
use App\Http\Resources\Collections\PositionDataResource;
use App\Models\Album;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PositionData
{
	public function get(AbstractAlbum $album, bool $include_sub_albums = false): PositionDataResource
	{
		$photo_relation = ($album instanceof Album && $include_sub_albums) ?
			$album->all_photos() :
			$album->photos();

		$photo_relation
			->with([
				'album' => function (BelongsTo $b): void {
					// The album is required for photos to properly
					// determine access and visibility rights; but we
					// don't need to determine the cover and thumbnail for
					// each album
					$b->without(['cover', 'thumb']);
				},
				'statistics',
				'size_variants' => function (HasMany $r): void {
					// The web GUI only uses the small and thumb size
					// variants to show photos on a map; so we can save
					// hydrating the larger size variants
					// this really helps, if you want to show thousands
					// of photos on a map, as there are up to 7 size
					// variants per photo
					$r->whereBetween('type', [SizeVariantType::SMALL2X, SizeVariantType::THUMB]);
				},
			])
			->whereNotNull('latitude')
			->whereNotNull('longitude');

		return new PositionDataResource($album->get_id(), $album->get_title(), $photo_relation->get(), $album instanceof Album ? $album->track_url : null);
	}
}
