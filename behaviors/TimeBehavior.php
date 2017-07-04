<?php

namespace float\behaviors;

use Yii;
use yii\base\Behavior;
use yii\db\ActiveRecord;

/**
 * Use this behaviour to convert time fields when reading from and saving to the database
 */
class TimeBehavior extends Behavior
{
    /**
     * Properties to convert
     *
     * @var string[]
     */
    public $properties;

    /**
     * The format to convert to after find
     *
     * @var string
     */
    public $findFormat = 'HH:mm';

    /**
     * The format to convert to before save
     *
     * @var string
     */
    public $saveFormat = 'HH:mm:ss';

    /**
     * {@inheritDoc}
     * @see \yii\base\Behavior::events()
     */
    public function events()
    {
        return [
            ActiveRecord::EVENT_AFTER_FIND    => 'findFormat',
            ActiveRecord::EVENT_AFTER_INSERT  => 'findFormat',
            ActiveRecord::EVENT_AFTER_REFRESH => 'findFormat',
            ActiveRecord::EVENT_AFTER_UPDATE  => 'findFormat',
            ActiveRecord::EVENT_BEFORE_INSERT => 'saveFormat',
            ActiveRecord::EVENT_BEFORE_UPDATE => 'saveFormat',
        ];
    }

    /**
     * Format the time after find
     *
     * @param unknown $event
     */
    public function findFormat($event)
    {
        foreach ($this->properties as $property) {
            if ($this->owner->$property !== null) {
                $this->owner->$property = Yii::$app->formatter->asTime($this->owner->$property, $this->findFormat);
            }
        }
    }

    /**
     * Format the time before save
     *
     * @param unknown $event
     */
    public function saveFormat($event)
    {
        foreach ($this->properties as $property) {
            if ($this->owner->$property !== null) {
                $this->owner->$property = Yii::$app->formatter->asTime($this->owner->$property, $this->saveFormat);
            }
        }
    }
}