<?php
namespace API\Middleware;

class Cache extends \Slim\Middleware
{
    public function __construct($root = '')
    {
        $this->root = $root;
        $this->ttl = 300; // 5 minutes
        
    }
    
    public function call()
    {
        $key = $this->app->request->getResourceUri();
        $response = $this->app->response;

        if ($ttl = $this->app->config('cache.ttl')) {
            $this->ttl = $ttl;
        }

        if (preg_match('|^' . $this->root . '.*|', $key)) {
            $method = strtolower($this->app->request->getMethod());
        
            if ('get' === $method) {
                $queryString = http_build_query($this->app->request->get());
                if (!empty($queryString)) {
                    $key .= '?' . $queryString;
                }
            
                $data = $this->fetch($key);
                if ($data) {
            
                    // Cache hit... return the cached content
                    $response->headers->set(
                        'Content-Type',
                        'application/json'
                    );
                    $response->headers->set(
                        'X-Cache',
                        'HIT'
                    );
                    try {
                    
                        $this->app->etag($data['checksum']);
                        $this->app->expires($data['expires']);
                        $response->body($data['content']);
                    } catch (\Slim\Exception\Stop $e) {
                    }
                    return;
                }
        
                // Cache miss... continue on to generate the page
                $this->next->call();
        
                if ($response->status() == 200) {
            
                    // Cache result for future look up
                    $checksum = md5($response->body());
                    $expires = time() + $this->ttl;
                
                    $this->save(
                        $key,
                        array(
                            'checksum' => $checksum,
                            'expires' => $expires,
                            'content' => $response->body(),
                        )
                    );

                    $response->headers->set(
                        'X-Cache',
                        'MISS'
                    );
                    try {
                        $this->app->etag($checksum);
                        $this->app->expires($expires);

                    } catch (\Slim\Exception\Stop $e) {
                    }
                    return;
                }

            } else {
                if ($response->status() == 200) {
                    $response->headers->set(
                        'X-Cache',
                        'NONE'
                    );
                    $this->clean($key);
                }
            }
            
        }
        $this->next->call();
    }
    
    protected function fetch($key)
    {
        return apc_fetch($key);
    }
    
    protected function save($key, $value)
    {
        apc_store($key, $value, $this->ttl);
    }

    protected function clean($key = '')
    {
        // Delete all keys beginning with $key
        if (!empty($key)) {
            $toDelete = new \APCIterator('user', '|^'.$key.'.*|', APC_ITER_KEY);
            return apc_delete($toDelete);
        }
        
        // Clean all user cache
        return apc_clear_cache('user');
    }
}
