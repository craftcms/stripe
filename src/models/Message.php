<?php

namespace craft\stripe\models;

use craft\base\Model;
use craft\i18n\Translation;

class Message extends Model
{
    /**
     * @var int|null
     */
    public ?int $id = null;

    /**
     * @var int|null
     */
    public ?int $subscriptionId = null;

    /**
     * @var string|null
     */
    public ?string $message = null;

    /**
     * @var \DateTime|null
     */
    public ?\DateTime $dateCreated = null;

    public function getLocalizedMessage(): string
    {
        return Translation::translate($this->message);
    }
}
