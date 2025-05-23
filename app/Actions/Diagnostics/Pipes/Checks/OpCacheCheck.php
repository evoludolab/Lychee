<?php

/**
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2017-2018 Tobias Reich
 * Copyright (c) 2018-2025 LycheeOrg.
 */

namespace App\Actions\Diagnostics\Pipes\Checks;

use App\Contracts\DiagnosticPipe;
use App\DTO\DiagnosticData;
use Safe\Exceptions\InfoException;
use function Safe\ini_get;

/**
 * We want to make sure that our users are using OPcache for faster php code execution.
 */
class OpCacheCheck implements DiagnosticPipe
{
	/**
	 * {@inheritDoc}
	 */
	public function handle(array &$data, \Closure $next): array
	{
		if (!$this->isOpcacheGetConfigurationAvailable()) {
			// @codeCoverageIgnoreStart
			$data[] = DiagnosticData::warn('opcache_get_configuration() is not available.', self::class, ['We are unable to check for performance optimizations.']);

			return $next($data);
			// @codeCoverageIgnoreEnd
		}

		$opcache_conf = opcache_get_configuration();

		if (
			!is_array($opcache_conf) ||
			!isset($opcache_conf['directives']['opcache.enable']) ||
			$opcache_conf['directives']['opcache.enable'] === '0'
		) {
			// @codeCoverageIgnoreStart
			$data[] = DiagnosticData::warn('OPcache is not enabled.', self::class, ['Enabling it will improve performance.']);
			// @codeCoverageIgnoreEnd
		}

		return $next($data);
	}

	/**
	 * Check if the `opcache_get_configuration` function is available.
	 */
	private function isOpcacheGetConfigurationAvailable(): bool
	{
		try {
			$disabled_functions = explode(',', ini_get('disable_functions'));

			return function_exists('opcache_get_configuration') && !in_array('opcache_get_configuration', $disabled_functions, true);
			// @codeCoverageIgnoreStart
		} catch (InfoException $e) {
			return false;
		}
		// @codeCoverageIgnoreEnd
	}
}
