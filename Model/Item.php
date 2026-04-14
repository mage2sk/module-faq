<?php
/**
 * FAQ Item Model
 *
 * @category  Panth
 * @package   Panth_Faq
 * @author    Panth
 * @copyright Copyright (c) 2025 Panth
 */
declare(strict_types=1);

namespace Panth\Faq\Model;

use Magento\Framework\Model\AbstractModel;
use Panth\Faq\Api\Data\ItemInterface;

class Item extends AbstractModel implements ItemInterface
{
    /**
     * Cache tag
     */
    const CACHE_TAG = 'panth_faq_item';

    /**
     * @var string
     */
    protected $_cacheTag = self::CACHE_TAG;

    /**
     * @var string
     */
    protected $_eventPrefix = 'panth_faq_item';

    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(\Panth\Faq\Model\ResourceModel\Item::class);
    }

    /**
     * Validate before save
     *
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function beforeSave()
    {
        parent::beforeSave();

        // Generate URL key if not set
        if (!$this->getUrlKey() && $this->getQuestion()) {
            $urlKey = $this->formatUrlKey($this->getQuestion());
            $this->setUrlKey($urlKey);
        }

        // Validate URL key uniqueness
        if ($this->getUrlKey()) {
            $this->validateUrlKey();
        }

        return $this;
    }

    /**
     * Format URL key from string
     *
     * @param string $string
     * @return string
     */
    protected function formatUrlKey($string)
    {
        $urlKey = strtolower($string);
        $urlKey = preg_replace('/[^a-z0-9]+/', '-', $urlKey);
        $urlKey = trim($urlKey, '-');
        return $urlKey;
    }

    /**
     * Validate URL key uniqueness
     *
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function validateUrlKey()
    {
        $urlKey = $this->getUrlKey();
        $collection = $this->getCollection()
            ->addFieldToFilter('url_key', $urlKey);

        // Exclude current item from check
        if ($this->getId()) {
            $collection->addFieldToFilter('item_id', ['neq' => $this->getId()]);
        }

        if ($collection->getSize() > 0) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('The URL key "%1" already exists. Please use a unique URL key.', $urlKey)
            );
        }
    }

    /**
     * Get ID
     *
     * @return int|null
     */
    public function getId()
    {
        return $this->getData(self::ITEM_ID);
    }

    /**
     * Get category ID
     *
     * @return int|null
     */
    public function getCategoryId()
    {
        return $this->getData(self::CATEGORY_ID);
    }

    /**
     * Get question
     *
     * @return string
     */
    public function getQuestion()
    {
        return $this->getData(self::QUESTION);
    }

    /**
     * Get answer
     *
     * @return string
     */
    public function getAnswer()
    {
        return $this->getData(self::ANSWER);
    }

    /**
     * Get URL key
     *
     * @return string|null
     */
    public function getUrlKey()
    {
        return $this->getData(self::URL_KEY);
    }

    /**
     * Get is active
     *
     * @return int
     */
    public function getIsActive()
    {
        return $this->getData(self::IS_ACTIVE);
    }

    /**
     * Get sort order
     *
     * @return int
     */
    public function getSortOrder()
    {
        return $this->getData(self::SORT_ORDER);
    }

    /**
     * Get view count
     *
     * @return int
     */
    public function getViewCount()
    {
        return $this->getData(self::VIEW_COUNT);
    }

    /**
     * Get helpful count
     *
     * @return int
     */
    public function getHelpfulCount()
    {
        return $this->getData(self::HELPFUL_COUNT);
    }

    /**
     * Get not helpful count
     *
     * @return int
     */
    public function getNotHelpfulCount()
    {
        return $this->getData(self::NOT_HELPFUL_COUNT);
    }

    /**
     * Get show on main
     *
     * @return int
     */
    public function getShowOnMain()
    {
        return $this->getData(self::SHOW_ON_MAIN);
    }

    /**
     * Get meta title
     *
     * @return string|null
     */
    public function getMetaTitle()
    {
        return $this->getData(self::META_TITLE);
    }

    /**
     * Get meta description
     *
     * @return string|null
     */
    public function getMetaDescription()
    {
        return $this->getData(self::META_DESCRIPTION);
    }

    /**
     * Get meta keywords
     *
     * @return string|null
     */
    public function getMetaKeywords()
    {
        return $this->getData(self::META_KEYWORDS);
    }

    /**
     * Get created at
     *
     * @return string
     */
    public function getCreatedAt()
    {
        return $this->getData(self::CREATED_AT);
    }

    /**
     * Get updated at
     *
     * @return string
     */
    public function getUpdatedAt()
    {
        return $this->getData(self::UPDATED_AT);
    }

    /**
     * Set ID
     *
     * @param int $id
     * @return $this
     */
    public function setId($id)
    {
        return $this->setData(self::ITEM_ID, $id);
    }

    /**
     * Set category ID
     *
     * @param int $categoryId
     * @return $this
     */
    public function setCategoryId($categoryId)
    {
        return $this->setData(self::CATEGORY_ID, $categoryId);
    }

    /**
     * Set question
     *
     * @param string $question
     * @return $this
     */
    public function setQuestion($question)
    {
        return $this->setData(self::QUESTION, $question);
    }

    /**
     * Set answer
     *
     * @param string $answer
     * @return $this
     */
    public function setAnswer($answer)
    {
        return $this->setData(self::ANSWER, $answer);
    }

    /**
     * Set URL key
     *
     * @param string $urlKey
     * @return $this
     */
    public function setUrlKey($urlKey)
    {
        return $this->setData(self::URL_KEY, $urlKey);
    }

    /**
     * Set is active
     *
     * @param int $isActive
     * @return $this
     */
    public function setIsActive($isActive)
    {
        return $this->setData(self::IS_ACTIVE, $isActive);
    }

    /**
     * Set sort order
     *
     * @param int $sortOrder
     * @return $this
     */
    public function setSortOrder($sortOrder)
    {
        return $this->setData(self::SORT_ORDER, $sortOrder);
    }

    /**
     * Set view count
     *
     * @param int $viewCount
     * @return $this
     */
    public function setViewCount($viewCount)
    {
        return $this->setData(self::VIEW_COUNT, $viewCount);
    }

    /**
     * Set helpful count
     *
     * @param int $helpfulCount
     * @return $this
     */
    public function setHelpfulCount($helpfulCount)
    {
        return $this->setData(self::HELPFUL_COUNT, $helpfulCount);
    }

    /**
     * Set not helpful count
     *
     * @param int $notHelpfulCount
     * @return $this
     */
    public function setNotHelpfulCount($notHelpfulCount)
    {
        return $this->setData(self::NOT_HELPFUL_COUNT, $notHelpfulCount);
    }

    /**
     * Set show on main
     *
     * @param int $showOnMain
     * @return $this
     */
    public function setShowOnMain($showOnMain)
    {
        return $this->setData(self::SHOW_ON_MAIN, $showOnMain);
    }

    /**
     * Set meta title
     *
     * @param string $metaTitle
     * @return $this
     */
    public function setMetaTitle($metaTitle)
    {
        return $this->setData(self::META_TITLE, $metaTitle);
    }

    /**
     * Set meta description
     *
     * @param string $metaDescription
     * @return $this
     */
    public function setMetaDescription($metaDescription)
    {
        return $this->setData(self::META_DESCRIPTION, $metaDescription);
    }

    /**
     * Set meta keywords
     *
     * @param string $metaKeywords
     * @return $this
     */
    public function setMetaKeywords($metaKeywords)
    {
        return $this->setData(self::META_KEYWORDS, $metaKeywords);
    }

    /**
     * Set created at
     *
     * @param string $createdAt
     * @return $this
     */
    public function setCreatedAt($createdAt)
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    /**
     * Set updated at
     *
     * @param string $updatedAt
     * @return $this
     */
    public function setUpdatedAt($updatedAt)
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
    }

    /**
     * Get identities
     *
     * @return array
     */
    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }
}
