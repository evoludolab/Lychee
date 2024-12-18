<?php

namespace App\Http\Requests\Traits;

trait HasOwnerIdTrait
{
	protected ?int $owner_id = null;

	/**
	 * @return int|null
	 */
	public function ownerId(): ?int
	{
		return $this->owner_id;
	}
}
