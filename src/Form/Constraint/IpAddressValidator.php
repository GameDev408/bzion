<?php
/**
 * This file contains a validator constraint for IP address form types
 *
 * @license    https://github.com/allejo/bzion/blob/master/LICENSE.md GNU General Public License Version 3
 */

namespace BZIon\Form\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Ip Address validator
 */
class IpAddressValidator extends ConstraintValidator
{
    /**
     * {@inheritDoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (empty($value)) {
            // No need to run any checks if no IP is provided
            return;
        }

        foreach ($value as $value) {
            if (strlen($value) > 255) {
                $this->context->buildViolation($constraint->lengthMessage)
                    ->setParameter('%address%', $value)
                    ->addViolation();
            }

            // Valid hostnames will get caught as valid hostnames, so there is
            // no need to check if $this->isValidIP
            if (!$this->isValidHostname($value)) {
                $this->context->buildViolation($constraint->message)
                    ->setParameter('%address%', $value)
                    ->addViolation();
            }
        }
    }

    /**
     * Finds out whether a string is a valid IP address
     * @param  $string The IP address to check
     *
     * @return boolean
     */
    private function isValidIP($string)
    {
        $pattern = '/^(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5]|\*)\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5]|\*)$/';

        return (boolean) preg_match($pattern, $string);
    }

    /**
     * Finds out whether a string is a valid hostname
     * @param  $string The hostname to check
     *
     * @return boolean
     */
    private function isValidHostname($string)
    {
        $pattern = '/^[a-z\d\*]([a-z\d\-\*]{0,61}[a-z\d\*])?(\.[a-z\d\*]([a-z\d\-\*]{0,61}[a-z\d\*])?)*$/';

        return (boolean) preg_match($pattern, $string);
    }
}
