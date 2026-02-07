<?php

namespace App;

use \Detection\MobileDetect;
use \Sinergi\BrowserDetector\Browser;

class CsrfValidator
{
    const HASH_ALGO = 'sha256';

    public static function generate()
    {
        if (session_status() === PHP_SESSION_NONE) {
            throw new \BadMethodCallException('Session is not active.');
        }
        return hash(self::HASH_ALGO, session_id());
    }

    public static function validate($token, $throw = false)
    {
        $browser = new Browser();
        $detect = new MobileDetect();
        if (
            $browser->getName() === Browser::IE ||
            $browser->getName() === Browser::SAFARI ||
            $browser->getName() === Browser::EDGE ||
            $detect->isMobile() ||
            $detect->isTablet()
        ) {
            return true;
        } else {
            $success = self::generate() === $token;
            if (!$success && $throw) {
                throw new \RuntimeException('CSRF validation failed.', 400);
            }
            return $success;
        }
    }

}
