<?php
namespace API\Middleware;

class RateLimit extends \Slim\Middleware
{

    public function __construct($root = '')
    {
        $this->root = $root;
        $this->max = 100; // Requests per hour
        
    }

    public function call()
    {
        $response = $this->app->response;
        $request = $this->app->request;

        if ($max = $this->app->config('rate.limit')) {
            $this->max = $max;
        }

        // Activate on given root URL only
        if (preg_match(
            '|^' . $this->root . '.*|',
            $this->app->request->getResourceUri()
        )) {


            // Use API key from the current user as ID
            if ($key = $this->app->user['apikey']) {
                
                $data = $this->fetch($key);
                if (false === $data) {
                    
                    // First time or previous perion expired,
                    // initialize and save a new entry
                    
                    $remaining = ($this->max -1);
                    $reset = 3600;
                    
                    $this->save(
                        $key,
                        array(
                            'remaining' => $remaining,
                            'created' => time()
                        ),
                        $reset
                    );
                } else {
                    
                    // Take the current entry and update it
                    
                    $remaining = (--$data['remaining'] >= 0)
                        ? $data['remaining'] : -1;
                    
                    $reset = (($data['created'] + 3600) - time());
                    
                    $this->save(
                        $key,
                        array(
                            'remaining' => $remaining,
                            'created' => $data['created']
                        ),
                        $reset
                    );
                }
                
                // Set rating headers
                
                $response->headers->set(
                    'X-Rate-Limit-Limit',
                    $this->max
                );
                
                $response->headers->set(
                    'X-Rate-Limit-Reset',
                    $reset
                );

                $response->headers->set(
                    'X-Rate-Limit-Remaining',
                    $remaining
                );

                // Check if the current key is allowed to pass
                if (0 > $remaining) {

                    // Rewrite remaining headers
                    $response->headers->set(
                        'X-Rate-Limit-Remaining',
                        0
                    );

                    // Exits with status "429 Too Many Requests" (see doc below)
                    $this->fail();
                }
                

            } else {
                // Exits with status "429 Too Many Requests" (see doc below)
                $this->fail();
            }


        }

        $this->next->call();
        
    }
    
    protected function fetch($key)
    {
        return apc_fetch($key);
    }
    
    protected function save($key, $value, $expire = 0)
    {
        apc_store($key, $value, $expire);
    }

    /**
     * Exits with status "429 Too Many Requests"
     * 
     * Work around on Apache's issue: it does not support
     * status code 429 until version 2.4
     * 
     * @link http://stackoverflow.com/questions/17735514/php-apache-silently-converting-http-429-and-others-to-500
     */
    protected function fail()
    {
        header('HTTP/1.1 429 Too Many Requests', false, 429);
        
        // Write the remaining headers
        foreach ($this->app->response->headers as $key => $value) {
            header($key . ': ' . $value);
        }
        exit;
    }
}
