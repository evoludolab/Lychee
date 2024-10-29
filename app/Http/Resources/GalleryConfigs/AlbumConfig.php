<?php

namespace App\Http\Resources\GalleryConfigs;

use App\Contracts\Models\AbstractAlbum;
use App\Enum\AspectRatioCSSType;
use App\Enum\AspectRatioType;
use App\Enum\PhotoLayoutType;
use App\Models\Album;
use App\Models\Configs;
use App\Models\Extensions\BaseAlbum;
use App\Policies\AlbumPolicy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript()]
class AlbumConfig extends Data
{
	public bool $is_base_album;
	public bool $is_model_album;
	public bool $is_accessible;
	public bool $is_password_protected;
	public bool $is_map_accessible;
	public bool $is_mod_frame_enabled;
	public bool $is_search_accessible;
	public bool $is_nsfw_warning_visible;
	public AspectRatioCSSType $album_thumb_css_aspect_ratio;
	public PhotoLayoutType $photo_layout;

	public function __construct(AbstractAlbum $album)
	{
		$is_accessible = Gate::check(AlbumPolicy::CAN_ACCESS, [AbstractAlbum::class, $album]);
		$public_perm = $album->public_permissions();

		$this->is_accessible = $is_accessible;
		$this->is_base_album = $album instanceof BaseAlbum;
		$this->is_model_album = $album instanceof Album;
		$this->is_password_protected = !$is_accessible && $public_perm?->password !== null;
		$this->is_nsfw_warning_visible =
			$album instanceof BaseAlbum &&
			$album->is_nsfw &&
			(Auth::check() ? Configs::getValueAsBool('nsfw_warning_admin') : Configs::getValueAsBool('nsfw_warning'));

		$this->setIsMapAccessible();
		$this->setIsSearchAccessible($this->is_base_album);
		$this->is_mod_frame_enabled = Configs::getValueAsBool('mod_frame_enabled') && $album->photos->count() > 0;
		if ($album instanceof Album && $album->album_thumb_aspect_ratio !== null) {
			$this->album_thumb_css_aspect_ratio = $album->album_thumb_aspect_ratio->css();
		} else {
			$this->album_thumb_css_aspect_ratio = Configs::getValueAsEnum('default_album_thumb_aspect_ratio', AspectRatioType::class)->css();
		}

		$this->photo_layout = (($album instanceof BaseAlbum) ? $album->photo_layout : null) ?? Configs::getValueAsEnum('layout', PhotoLayoutType::class);
	}

	public function setIsMapAccessible(): void
	{
		$map_display = Configs::getValueAsBool('map_display');
		$public_display = Auth::check() || Configs::getValueAsBool('map_display_public');
		$this->is_map_accessible = $map_display && $public_display;
	}

	public function setIsSearchAccessible(bool $is_base_album): void
	{
		$this->is_search_accessible = (Auth::check() || Configs::getValueAsBool('search_public')) && $is_base_album;
	}
}