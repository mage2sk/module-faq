<?php
/**
 * FAQ Item Interface
 *
 * @category  Panth
 * @package   Panth_Faq
 * @author    Panth
 * @copyright Copyright (c) 2025 Panth
 */
declare(strict_types=1);

namespace Panth\Faq\Api\Data;

interface ItemInterface
{
    const ITEM_ID = 'item_id';
    const CATEGORY_ID = 'category_id';
    const QUESTION = 'question';
    const ANSWER = 'answer';
    const URL_KEY = 'url_key';
    const IS_ACTIVE = 'is_active';
    const SORT_ORDER = 'sort_order';
    const VIEW_COUNT = 'view_count';
    const HELPFUL_COUNT = 'helpful_count';
    const NOT_HELPFUL_COUNT = 'not_helpful_count';
    const SHOW_ON_MAIN = 'show_on_main';
    const META_TITLE = 'meta_title';
    const META_DESCRIPTION = 'meta_description';
    const META_KEYWORDS = 'meta_keywords';
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    /**
     * Get ID
     *
     * @return int|null
     */
    public function getId();

    /**
     * Get category ID
     *
     * @return int|null
     */
    public function getCategoryId();

    /**
     * Get question
     *
     * @return string
     */
    public function getQuestion();

    /**
     * Get answer
     *
     * @return string
     */
    public function getAnswer();

    /**
     * Get URL key
     *
     * @return string|null
     */
    public function getUrlKey();

    /**
     * Get is active
     *
     * @return int
     */
    public function getIsActive();

    /**
     * Get sort order
     *
     * @return int
     */
    public function getSortOrder();

    /**
     * Get view count
     *
     * @return int
     */
    public function getViewCount();

    /**
     * Get helpful count
     *
     * @return int
     */
    public function getHelpfulCount();

    /**
     * Get not helpful count
     *
     * @return int
     */
    public function getNotHelpfulCount();

    /**
     * Get show on main
     *
     * @return int
     */
    public function getShowOnMain();

    /**
     * Get meta title
     *
     * @return string|null
     */
    public function getMetaTitle();

    /**
     * Get meta description
     *
     * @return string|null
     */
    public function getMetaDescription();

    /**
     * Get meta keywords
     *
     * @return string|null
     */
    public function getMetaKeywords();

    /**
     * Get created at
     *
     * @return string
     */
    public function getCreatedAt();

    /**
     * Get updated at
     *
     * @return string
     */
    public function getUpdatedAt();

    /**
     * Set ID
     *
     * @param int $id
     * @return $this
     */
    public function setId($id);

    /**
     * Set category ID
     *
     * @param int $categoryId
     * @return $this
     */
    public function setCategoryId($categoryId);

    /**
     * Set question
     *
     * @param string $question
     * @return $this
     */
    public function setQuestion($question);

    /**
     * Set answer
     *
     * @param string $answer
     * @return $this
     */
    public function setAnswer($answer);

    /**
     * Set URL key
     *
     * @param string $urlKey
     * @return $this
     */
    public function setUrlKey($urlKey);

    /**
     * Set is active
     *
     * @param int $isActive
     * @return $this
     */
    public function setIsActive($isActive);

    /**
     * Set sort order
     *
     * @param int $sortOrder
     * @return $this
     */
    public function setSortOrder($sortOrder);

    /**
     * Set view count
     *
     * @param int $viewCount
     * @return $this
     */
    public function setViewCount($viewCount);

    /**
     * Set helpful count
     *
     * @param int $helpfulCount
     * @return $this
     */
    public function setHelpfulCount($helpfulCount);

    /**
     * Set not helpful count
     *
     * @param int $notHelpfulCount
     * @return $this
     */
    public function setNotHelpfulCount($notHelpfulCount);

    /**
     * Set show on main
     *
     * @param int $showOnMain
     * @return $this
     */
    public function setShowOnMain($showOnMain);

    /**
     * Set meta title
     *
     * @param string $metaTitle
     * @return $this
     */
    public function setMetaTitle($metaTitle);

    /**
     * Set meta description
     *
     * @param string $metaDescription
     * @return $this
     */
    public function setMetaDescription($metaDescription);

    /**
     * Set meta keywords
     *
     * @param string $metaKeywords
     * @return $this
     */
    public function setMetaKeywords($metaKeywords);

    /**
     * Set created at
     *
     * @param string $createdAt
     * @return $this
     */
    public function setCreatedAt($createdAt);

    /**
     * Set updated at
     *
     * @param string $updatedAt
     * @return $this
     */
    public function setUpdatedAt($updatedAt);
}
