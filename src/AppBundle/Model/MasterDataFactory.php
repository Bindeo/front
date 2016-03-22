<?php

namespace AppBundle\Model;

use AppBundle\Entity\ResultSet;
use Bindeo\Util\ApiConnection;

class MasterDataFactory
{
    private $api;
    private $masterData;

    public function __construct(ApiConnection $api)
    {
        $this->api = $api;
        $this->masterData = [];
    }

    /**
     * Get or create the requested master data
     *
     * @param string $type
     * @param string $locale
     *
     * @return ResultSet
     */
    private function getData($type, $locale)
    {
        // If master data exists we return it
        if (!isset($this->masterData[$type][$locale])) {
            $key = $type . '_' . $locale;

            // We look for it in cache
            if (apc_exists($key)) {
                $this->masterData[$type][$locale] = apc_fetch($key);
            } else {
                // We need to retrieve data from the API
                if ($type == 'accountType') {
                    $route = 'general_account_types';
                } elseif ($type == 'fileType') {
                    $route = 'general_file_types';
                } elseif ($type == 'mediaType') {
                    $route = 'general_media_types';
                } else {
                    return null;
                }

                // Get from the API
                $this->masterData[$type][$locale] = $this->api->getJson($route, ['locale' => $locale]);
                // And save it to cache
                apc_store($key, $this->masterData[$type][$locale]);
            }
        }

        return $this->masterData[$type][$locale];
    }

    /**
     * Get the account type master data in the given locale
     *
     * @param string $locale
     *
     * @return ResultSet
     */
    public function createAccountType($locale)
    {
        return $this->getData('accountType', $locale);
    }

    /**
     * Get the file type master data in the given locale
     *
     * @param string $locale
     *
     * @return ResultSet
     */
    public function createFileType($locale)
    {
        return $this->getData('fileType', $locale);
    }

    /**
     * Get the media type master data in the given locale
     *
     * @param string $locale
     *
     * @return ResultSet
     */
    public function createMediaType($locale)
    {
        return $this->getData('mediaType', $locale);
    }
}