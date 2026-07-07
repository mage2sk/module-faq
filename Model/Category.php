<?php
declare(strict_types=1);

namespace Panth\Faq\Model;

use Magento\Framework\Model\AbstractModel;
use Panth\Faq\Api\Data\CategoryInterface;

class Category extends AbstractModel implements CategoryInterface
{
    const CACHE_TAG = 'panth_faq_category';

    protected $_cacheTag = self::CACHE_TAG;

    protected $_eventPrefix = 'panth_faq_category';

    protected function _construct()
    {
        $this->_init(\Panth\Faq\Model\ResourceModel\Category::class);
    }

    public function beforeSave()
    {
        parent::beforeSave();

        if (!$this->getUrlKey() && $this->getName()) {
            $urlKey = $this->formatUrlKey($this->getName());
            $this->setUrlKey($urlKey);
        }

        if ($this->getUrlKey()) {
            $this->validateUrlKey();
        }

        return $this;
    }

    protected function formatUrlKey($string)
    {
        $urlKey = strtolower($string);
        $urlKey = preg_replace('/[^a-z0-9]+/', '-', $urlKey);
        $urlKey = trim($urlKey, '-');
        return $urlKey;
    }

    protected function validateUrlKey()
    {
        $urlKey = $this->getUrlKey();
        $collection = $this->getCollection()
            ->addFieldToFilter('url_key', $urlKey);

        if ($this->getId()) {
            $collection->addFieldToFilter('category_id', ['neq' => $this->getId()]);
        }

        if ($collection->getSize() > 0) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('The URL key "%1" already exists. Please use a unique URL key.', $urlKey)
            );
        }
    }

    public function getId()
    {
        return $this->getData(self::CATEGORY_ID);
    }

    public function getName()
    {
        return $this->getData(self::NAME);
    }

    public function getUrlKey()
    {
        return $this->getData(self::URL_KEY);
    }

    public function getDescription()
    {
        return $this->getData(self::DESCRIPTION);
    }

    public function getIcon()
    {
        return $this->getData(self::ICON);
    }

    public function getIsActive()
    {
        return $this->getData(self::IS_ACTIVE);
    }

    public function getSortOrder()
    {
        return $this->getData(self::SORT_ORDER);
    }

    public function getMetaTitle()
    {
        return $this->getData(self::META_TITLE);
    }

    public function getMetaDescription()
    {
        return $this->getData(self::META_DESCRIPTION);
    }

    public function getMetaKeywords()
    {
        return $this->getData(self::META_KEYWORDS);
    }

    public function getCreatedAt()
    {
        return $this->getData(self::CREATED_AT);
    }

    public function getUpdatedAt()
    {
        return $this->getData(self::UPDATED_AT);
    }

    public function setId($id)
    {
        return $this->setData(self::CATEGORY_ID, $id);
    }

    public function setName($name)
    {
        return $this->setData(self::NAME, $name);
    }

    public function setUrlKey($urlKey)
    {
        return $this->setData(self::URL_KEY, $urlKey);
    }

    public function setDescription($description)
    {
        return $this->setData(self::DESCRIPTION, $description);
    }

    public function setIcon($icon)
    {
        return $this->setData(self::ICON, $icon);
    }

    public function setIsActive($isActive)
    {
        return $this->setData(self::IS_ACTIVE, $isActive);
    }

    public function setSortOrder($sortOrder)
    {
        return $this->setData(self::SORT_ORDER, $sortOrder);
    }

    public function setMetaTitle($metaTitle)
    {
        return $this->setData(self::META_TITLE, $metaTitle);
    }

    public function setMetaDescription($metaDescription)
    {
        return $this->setData(self::META_DESCRIPTION, $metaDescription);
    }

    public function setMetaKeywords($metaKeywords)
    {
        return $this->setData(self::META_KEYWORDS, $metaKeywords);
    }

    public function setCreatedAt($createdAt)
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    public function setUpdatedAt($updatedAt)
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
    }

    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }
}
