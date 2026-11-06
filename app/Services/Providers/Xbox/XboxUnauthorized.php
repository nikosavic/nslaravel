<?php

namespace App\Services\Providers\Xbox;

use App\Services\Providers\Exceptions\ProviderException;

/** Xbox Live answered 401: the short-lived token is no longer good and should be renewed once. */
class XboxUnauthorized extends ProviderException
{
}
