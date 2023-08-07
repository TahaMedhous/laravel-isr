<?php

namespace Tahamed\LaravelIsr;

use Illuminate\Support\Facades\Cache;


class ISRController
{
    public function GetData(int $id, callable $dataCallback, int $duration, string $view, string $customDataName = null)
    {
        $cacheKey = 'data_' . $id;
        $cachedData = Cache::get($cacheKey) ?? [];
        $pageData = $cachedData['data'] ?? null;
        $timestamp = $cachedData['timestamp'] ?? null;
        $currentTime = time();

        if ($pageData === null && $timestamp === null) {
            $pageData = call_user_func($dataCallback, $id);

            if (!empty($pageData)) {
                $cachedData = ['data' => $pageData, 'timestamp' => $currentTime];
                Cache::put($cacheKey, $cachedData, $duration);
            } else {
                return view('404');
            }
        } elseif ($pageData && ($currentTime - $timestamp) >= $duration) {
            $newPageData = call_user_func($dataCallback, $id);

            if (empty($newPageData)) {
                // the page doesn't exist anymore, so we'll delete the cached data
                Cache::forget($cacheKey);
                return view('404');
            } elseif (
                $this->hasDataChanged($pageData, $newPageData)
            ) {
                $cachedData = ['data' => $newPageData, 'timestamp' => $currentTime];
                Cache::put($cacheKey, $cachedData, $duration);
                $pageData = $newPageData;
            }
        }

        if ($customDataName !== null) {
            // If a custom data name is provided, we'll use that instead of the default 'pageData'
            // so when we pass the data to the view, we can use the custom name instead of 'pageData'
            $pageData = [$customDataName => $pageData];
        } else {
            $pageData = ['pageData' => $pageData];
        }

        return view($view, $pageData);
    }

    private function hasDataChanged(array $oldData, array $newData): bool
    {
        $serializedOldData = serialize($oldData);
        $serializedNewData = serialize($newData);

        return $serializedOldData !== $serializedNewData;
    }
}
