<?php
namespace API\Middleware;

class JSON extends \Slim\Middleware
{
    public function __construct($root = '')
    {
        $this->root = $root;
    }
    
    public function call()
    {
        if (preg_match(
            '|^' . $this->root . '.*|',
            $this->app->request->getResourceUri()
        )) {

            // Force response headers to JSON
            $this->app->response->headers->set(
                'Content-Type',
                'application/json'
            );
            
            $method = strtolower($this->app->request->getMethod());
            $mediaType = $this->app->request->getMediaType();

            if (in_array(
                $method,
                array('post', 'put', 'patch')
            ) && '' !== $this->app->request()->getBody()) {
                
                if (empty($mediaType)
                    || $mediaType !== 'application/json') {
                    $this->app->halt(415);
                }
            }
        }
        $this->next->call();
    }
}
