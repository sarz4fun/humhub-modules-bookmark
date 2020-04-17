<?php

/**
 * @link https://www.humhub.org/
 * @copyright Copyright (c) 2015 HumHub GmbH & Co. KG
 * @license https://www.humhub.com/licences
 */

namespace humhub\modules\bookmark\models;

use humhub\modules\bookmark\models\forms\DefaultSettings;
use Yii;
use humhub\modules\content\components\ContentAddonActiveRecord;
use humhub\modules\content\interfaces\ContentOwner;
use humhub\modules\bookmark\notifications\NewBookmark;

/**
 * This is the model class for table "bookmark".
 *
 * The followings are the available columns in table 'bookmark':
 * @property integer $id
 * @property string $object_model
 * @property integer $object_id
 * @property string $created_at
 * @property integer $created_by
 * @property string $updated_at
 * @property integer $updated_by
 *
 */
class Bookmark extends ContentAddonActiveRecord
{

    /**
     * @inheritdoc
     */
    protected $updateContentStreamSort = false;

    /**
     * @return string the associated database table name
     */
    public static function tableName()
    {
        return 'bookmark';
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            [
                'class' => \humhub\components\behaviors\PolymorphicRelation::class,
                'mustBeInstanceOf' => [
                    \yii\db\ActiveRecord::class,
                ]
            ]
        ];
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        return [
            [['object_model', 'object_id'], 'required'],
            [['id', 'object_id'], 'integer'],
        ];
    }

    /**
     * Bookmark Count for specific model
     */
    public static function GetBookmarks($objectModel, $objectId)
    {
        $cacheId = "bookmarks_" . $objectModel . "_" . $objectId;
        $cacheValue = Yii::$app->cache->get($cacheId);

        if ($cacheValue === false) {
            $newCacheValue = Bookmark::findAll(['object_model' => $objectModel, 'object_id' => $objectId]);
            Yii::$app->cache->set($cacheId, $newCacheValue, Yii::$app->settings->get('cache.expireTime'));
            return $newCacheValue;
        } else {
            return $cacheValue;
        }
    }

    /**
     * After Save, delete BookmarkCount (Cache) for target object
     */
    public function afterSave($insert, $changedAttributes)
    {
        Yii::$app->cache->delete('bookmarks_' . $this->object_model . "_" . $this->object_id);

        if (!DefaultSettings::instantiate()->private_bookmarking) {
            $this->createNotification();
        }

        return parent::afterSave($insert, $changedAttributes);
    }

    /**
     * Before Delete, remove BookmarkCount (Cache) of target object.
     * Remove activity
     */
    public function beforeDelete()
    {
        Yii::$app->cache->delete('bookmarks_' . $this->object_model . "_" . $this->object_id);

        return parent::beforeDelete();
    }



    /**
     * Returns the bookmarked record e.g. a Post
     *
     * @return \humhub\modules\content\components\ContentActiveRecord
     */
    public function getBookmarkedRecord()
    {
        return $this->content->getPolymorphicRelation();
    }

    private function createNotification()
    {
        if ($this->getSource() instanceof ContentOwner && $this->getSource()->content->createdBy !== null) {
            // This is required for comments where $this->getSource()->createdBy contains the comment author.
            $target = isset($this->getSource()->createdBy) ? $this->getSource()->createdBy : $this->getSource()->content->createdBy;
            NewBookmark::instance()->from(Yii::$app->user->getIdentity())->about($this)->send($target);
        }
    }

}
