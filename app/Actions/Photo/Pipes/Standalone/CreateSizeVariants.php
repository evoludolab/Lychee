<?php

/**
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2017-2018 Tobias Reich
 * Copyright (c) 2018-2025 LycheeOrg.
 */

namespace App\Actions\Photo\Pipes\Standalone;

use App\Contracts\Models\SizeVariantFactory;
use App\Contracts\PhotoCreate\StandalonePipe;
use App\DTO\PhotoCreate\StandaloneDTO;
use App\Exceptions\Handler;

class CreateSizeVariants implements StandalonePipe
{
	public function handle(StandaloneDTO $state, \Closure $next): StandaloneDTO
	{
		// Create remaining size variants if we were able to successfully
		// extract a reference image
		if ($state->source_image?->isLoaded()) {
			try {
				$size_variant_factory = resolve(SizeVariantFactory::class);
				$size_variant_factory->init($state->photo, $state->source_image, $state->naming_strategy);
				$size_variant_factory->createSizeVariants();
				// @codeCoverageIgnoreStart
			} catch (\Throwable $t) {
				// Don't re-throw the exception, because we do not want the
				// import to fail completely only due to missing size variants.
				// There are just too many options why the creation of size
				// variants may fail.
				Handler::reportSafely($t);
			}
			// @codeCoverageIgnoreEnd
		}

		return $next($state);
	}
}