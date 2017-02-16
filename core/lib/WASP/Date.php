<?php

namespace WASP;

use DateTime;
use DateInterval;
use InvalidArgumentException;
use WASP\Debug\Logger;

class Date
{
    public function copy($str)
    {
        if ($str instanceof DateTime)
            return DateTime::createFromFormat(DateTime::ATOM, $str->format(DateTime::ATOM));
        if ($str instanceof DateTimeImmutable)
            return DateTimeImmutable::createFromFormat(DateTime::ATOM, $str->format(DateTime::ATOM));

        if ($str instanceof DateInterval)
        {
            $fmt = 'P' . $str->y . 'Y' . $str->m . 'M' . $str->d . 'DT' . $str->h . 'H' . str->i . 'M' . $str->s . 'S';
            $int = new DateInterval($fmt);
            $int->invert = $str->invert;
            $int->days = $str->days;
            return $int;
        }

        throw new InvalidArgumentException("Invalid argument: " . Logger::str($str));
    }

    public function compareInterval(DateInterval $l, DateInterval $r)
    {
        $now = new \DateTimeImmutable();
        $a = $now->add($l);
        $b = $now->add($r);

        if ($a < $b)
            return -1;
        if ($a > $b)
            return 1;
        return 0;
    }

    public function lessThan(DateInterval $l, DateInterval $r)
    {
        return self::compareInterval($l, $r) < 0;
    }

    public function lessThanOrEqual(DateInterval $l, DateInterval $r)
    {
        return self::compareInterval($l, $r) <= 0;
    }

    public function greaterThan(DateInterval $l, DateInterval $r)
    {
        return self::compareInterval($l, $r) > 0;
    }

    public function greaterThanOrEqual(DateInterval $l, DateInterval $r)
    {
        return self::compareInterval($l, $r) >= 0;
    }

    public function isBefore(DateTime $l, DateTime $r)
    {
        return $l < $r;
    }

    public function isAfter(DateTime $l, DateTime $r)
    {
        return $l > $r;
    }

    public function isPast(DateTime $l)
    {
        $now = new DateTime();
        return $l < $now;
    }

    public function isFuture(DateTime $l)
    {
        $now = new DateTime();
        return $l > $now;
    }
}
