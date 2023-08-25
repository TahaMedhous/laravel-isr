<?php

namespace Tahamed\LaravelIsr;

use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\View\View;

class ISRController
{
    /**
     * Get data and render the specified view with cached data if available.
     *
     * @param string   $param
     * @param callable $dataCallback The callback function to retrieve data.
     * @param int      $duration      Cache duration in seconds.
     * @param string   $view          The view to render.
     * @param string   $customDataName Custom name for data passed to the view.
     * @return view\Illuminate\Contracts\View\View 
     */
    public function getPageData(string $param, callable $dataCallback, int $duration, string $view, string $customDataName): View
    {
        if (!$this->argsValid($param, $dataCallback, $duration, $view, $customDataName)) {
            throw new \InvalidArgumentException('Invalid arguments passed to getData() method.');
        }
        $cacheKey = 'data_' . $param;
        $cachedData = Cache::get($cacheKey, []);
        $pageData = $cachedData['data'] ?? null;
        $timestamp = $cachedData['timestamp'] ?? null;
        $currentTime = time();

        if ($pageData === null && $timestamp === null) {
            // if data is not cached, page was never generated before, generate it now.
            $pageData = call_user_func($dataCallback, $param);

            if (!empty($pageData)) {
                $this->storeInCache($cacheKey, $pageData, $currentTime, $duration);
            } else {
                return view('404');
            }
        } elseif ($pageData && ($currentTime - $timestamp) >= $duration) {
            // if data is cached but expired, fetch new data, store it in cache and render the view.
            $newPageData = call_user_func($dataCallback, $param);

            if (empty($newPageData)) {
                // if new data is empty (page data was deleted), delete cached data and render 404 page.
                Cache::forget($cacheKey);
                return view('404');
            } elseif ($this->hasDataChanged($pageData, $newPageData)) {
                $this->storeInCache($cacheKey, $newPageData, $currentTime, $duration);
                $pageData = $newPageData;
            }
        }

        $pageData = $this->preparePageData($pageData, $customDataName);

        return view($view, $pageData);
    }

    private function storeInCache(string $cacheKey, array $data, int $timestamp, int $duration)
    {
        // Store data in cache with timestamp to check later if data has changed.
        $cachedData = ['data' => $data, 'timestamp' => $timestamp];
        Cache::put($cacheKey, $cachedData, $duration);
    }

    private function preparePageData(array $pageData, string $customDataName): array
    {
        // Wrap page data in an array with a custom name to pass to the view.
        return [$customDataName => $pageData];
    }

    private function hasDataChanged(array $oldData, array $newData): bool
    {
        // when serializing an array, the order of the elements is can result in different output.
        // so we sort the arrays before serializing them to get the same output if the data is the same.
        ksort($oldData);
        ksort($newData);

        return md5(serialize($oldData)) !== md5(serialize($newData));
    }

    private function argsValid(string $param, callable $dataCallback, int $duration, string $view, string $customDataName): bool
    {
        return $param  !== '' && $duration > 0 && $view !== '' && $customDataName !== '' && is_callable($dataCallback);
    }
}
