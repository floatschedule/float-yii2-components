<?php

namespace float\validators;

use Yii;

/**
 * Validates that the value is a multiple of the specified increment
 */
class IncrementValidator extends \yii\validators\Validator
{
    public $incrementBy;

    /**
     *
     * {@inheritDoc}
     * @see \yii\validators\Validator::init()
     */
    public function init()
    {
        parent::init();

        if (!is_numeric($this->incrementBy)) {
            throw new InvalidConfigException('The "incrementBy" property must be set.');
        }

        if ($this->message === null) {
            $this->message = Yii::t('yii', '{attribute} must be an increment of {incrementBy}.');
        }
    }

    /**
     * {@inheritDoc}
     * @see \yii\validators\Validator::validateValue()
     */
    protected function validateValue($value)
    {
        if (fmod($value, $this->incrementBy) == 0) {
            return null;
        }

        return [$this->message, ['incrementBy' => $this->incrementBy]];
    }
}
