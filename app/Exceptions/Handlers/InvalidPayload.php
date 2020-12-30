<?php

namespace App\Exceptions\Handlers;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

class InvalidPayload
{
	/**
	 * Render an exception into an HTTP response.
	 *
	 * @param Request   $request
	 * @param Throwable $exception
	 *
	 * @return bool
	 */
	public function check($request, Throwable $exception)
	{
		return $exception instanceof DecryptException;
	}

	/**
	 * @return Response
	 */
	// @codeCoverageIgnoreStart
	public function go()
	{
		return response()->json(['error' => 'Session timed out'], 400);
	}

	// @codeCoverageIgnoreEnd
}
