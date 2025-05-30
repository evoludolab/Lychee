<?php

/**
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2017-2018 Tobias Reich
 * Copyright (c) 2018-2025 LycheeOrg.
 */

namespace App\Actions\Photo\Pipes\Shared;

use App\Assets\Features;
use App\Contracts\PhotoCreate\PhotoDTO;
use App\Contracts\PhotoCreate\PhotoPipe;
use App\Enum\SizeVariantType;
use App\Jobs\UploadSizeVariantToS3Job;
use App\Models\Configs;
use App\Models\SizeVariant;

/**
 * Upload SizeVariants to S3 once done (if enabled).
 * Note that we first create the job, then we dispatch it.
 * This allows to manage the queue properly and see it in the feedback.
 */
class UploadSizeVariantsToS3 implements PhotoPipe
{
	public function handle(PhotoDTO $state, \Closure $next): PhotoDTO
	{
		if (Features::active('use-s3')) {
			// @codeCoverageIgnoreStart
			$use_job_queues = Configs::getValueAsBool('use_job_queues');

			$jobs = $state->getPhoto()->size_variants->toCollection()
				->filter(fn ($v) => $v !== null && $v->type !== SizeVariantType::PLACEHOLDER)
				->map(fn (SizeVariant $variant) => new UploadSizeVariantToS3Job($variant));

			$jobs->each(fn ($job) => $use_job_queues ? dispatch($job) : dispatch_sync($job));
			// @codeCoverageIgnoreEnd
		}

		return $next($state);
	}
}
