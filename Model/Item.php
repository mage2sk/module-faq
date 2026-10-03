<?php
declare(strict_types=1);

namespace Panth\Faq\Model;

use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Model\AbstractModel;
use Panth\Faq\Api\Data\ItemInterface;

class Item extends AbstractModel implements ItemInterface, IdentityInterface
{
    const CACHE_TAG = 'panth_faq_item';

    protected $_cacheTag = self::CACHE_TAG;

    protected $_eventPrefix = 'panth_faq_item';

    protected function _construct()
    {
        $this->_init(\Panth\Faq\Model\ResourceModel\Item::class);
    }

    public function beforeSave()
    {
        parent::beforeSave();

        if (!$this->getUrlKey() && $this->getQuestion()) {
            $urlKey = $this->formatUrlKey($this->getQuestion());
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
            $collection->addFieldToFilter('item_id', ['neq' => $this->getId()]);
        }

        if ($collection->getSize() > 0) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('The URL key "%1" already exists. Please use a unique URL key.', $urlKey)
            );
        }
    }

    public function getId()
    {
        return $this->getData(self::ITEM_ID);
    }

    public function getCategoryId()
    {
        return $this->getData(self::CATEGORY_ID);
    }

    public function getQuestion()
    {
        return $this->getData(self::QUESTION);
    }

    public function getAnswer()
    {
        return $this->getData(self::ANSWER);
    }

    public function getUrlKey()
    {
        return $this->getData(self::URL_KEY);
    }

    public function getIsActive()
    {
        return $this->getData(self::IS_ACTIVE);
    }

    public function getSortOrder()
    {
        return $this->getData(self::SORT_ORDER);
    }

    public function getViewCount()
    {
        return $this->getData(self::VIEW_COUNT);
    }

    public function getHelpfulCount()
    {
        return $this->getData(self::HELPFUL_COUNT);
    }

    public function getNotHelpfulCount()
    {
        return $this->getData(self::NOT_HELPFUL_COUNT);
    }

    public function getShowOnMain()
    {
        return $this->getData(self::SHOW_ON_MAIN);
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
        return $this->setData(self::ITEM_ID, $id);
    }

    public function setCategoryId($categoryId)
    {
        return $this->setData(self::CATEGORY_ID, $categoryId);
    }

    public function setQuestion($question)
    {
        return $this->setData(self::QUESTION, $question);
    }

    public function setAnswer($answer)
    {
        return $this->setData(self::ANSWER, $answer);
    }

    public function setUrlKey($urlKey)
    {
        return $this->setData(self::URL_KEY, $urlKey);
    }

    public function setIsActive($isActive)
    {
        return $this->setData(self::IS_ACTIVE, $isActive);
    }

    public function setSortOrder($sortOrder)
    {
        return $this->setData(self::SORT_ORDER, $sortOrder);
    }

    public function setViewCount($viewCount)
    {
        return $this->setData(self::VIEW_COUNT, $viewCount);
    }

    public function setHelpfulCount($helpfulCount)
    {
        return $this->setData(self::HELPFUL_COUNT, $helpfulCount);
    }

    public function setNotHelpfulCount($notHelpfulCount)
    {
        return $this->setData(self::NOT_HELPFUL_COUNT, $notHelpfulCount);
    }

    public function setShowOnMain($showOnMain)
    {
        return $this->setData(self::SHOW_ON_MAIN, $showOnMain);
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
        $identities = [self::CACHE_TAG . '_' . $this->getId(), self::CACHE_TAG];
        $relations = ['products' => 'cat_p_', 'catalog_categories' => 'cat_c_', 'pages' => 'cms_p_'];
        foreach ($relations as $key => $prefix) {
            foreach ([$this->getData($key), $this->getOrigData($key)] as $ids) {
                if (is_string($ids) && $ids !== '') {
                    $decoded = json_decode($ids, true);
                    $ids = is_array($decoded) ? $decoded : explode(',', $ids);
                }
                if (!is_array($ids)) {
                    continue;
                }
                foreach ($ids as $id) {
                    if (is_scalar($id) && (int)$id > 0) {
                        $identities[] = $prefix . (int)$id;
                    }
                }
            }
        }

        return array_values(array_unique($identities));
    }
}
