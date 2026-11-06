<?php

namespace App\Services\Providers\Exceptions;

/** The provider login is no longer valid and the user has to sign in again (e.g. expired NPSSO token). */
class ProviderAuthException extends ProviderException
{
}
