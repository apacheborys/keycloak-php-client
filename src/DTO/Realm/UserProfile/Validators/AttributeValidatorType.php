<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\DTO\Realm\UserProfile\Validators;

enum AttributeValidatorType: string
{
    case LENGTH = 'length';
    case DOUBLE = 'double';
    case EMAIL = 'email';
    case INTEGER = 'integer';
    case ISO_DATE = 'iso-date';
    case LOCAL_DATE = 'local-date';
    case MULTIVALUED = 'multivalued';
    case OPTIONS = 'options';
    case PATTERN = 'pattern';
    case PERSON_NAME_PROHIBITED_CHARACTERS = 'person-name-prohibited-characters';
    case UP_USERNAME_NOT_IDN_HOMOGRAPH = 'up-username-not-idn-homograph';
    case URI = 'uri';
    case USERNAME_PROHIBITED_CHARACTERS = 'username-prohibited-characters';
}
