<?php

namespace App\SmartAlbums;

use App\Models\Configs;
use App\Models\Photo;
use Illuminate\Database\Eloquent\Builder;

class StarredAlbum extends SmartAlbum
{
	public function __construct()
	{
		parent::__construct();

		$this->title = 'starred';
	}

	public function get_photos(): Builder
	{
		return Photo::stars()->where(fn ($q) => $this->filter($q));
	}

	public function is_public()
	{
		return Configs::get_value('public_starred', '0') === '1';
	}
}
