<?php
/**
 * Token Over HTTP Basic Authentication
 *
 * Use this middleware with your Slim Framework application
 * to require a user name or API key via HTTP basic auth
 * for all routes. No need for password.
 *
 * NOTE: the verify() protected method requires an ORM object,
 * Idiorm is the default.
 *
 *
 * @author Vito Tardia <vito@tardia.me>
 * @version 1.0
 * @copyright 2014 Vito Tardia
 *
 * USAGE
 *
 * $app = new \Slim\Slim();
 * $app->add(new API\Middleware\TokenOverBasicAuth());
 *
 * MIT LICENSE
 *
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to
 * the following conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
 * LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
 * OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
 * WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

namespace API\Middleware;

class TokenOverBasicAuth extends \Slim\Middleware
{
    /**
     * @var array
     */
    protected $settings = array(
        'realm' => 'Protected Area',
        'root'  => '/'
    );

    /**
     * Constructor
     *
     * @param   array  $config   Configuration and Login Details
     * @return  void
     */
    public function __construct(array $config = array())
    {
        if (!isset($this->app)) {
            $this->app = \Slim\Slim::getInstance();
        }
        $this->config = array_merge($this->settings, $config);
    }

    /**
     * Call
     *
     * This method will check the HTTP request headers for 
     * previous authentication. If the request has already authenticated,
     * the next middleware is called. Otherwise,
     * a 401 Authentication Required response is returned to the client.
     *
     * @return  void
     */
    public function call()
    {
        $req = $this->app->request();
        $res = $this->app->response();

        if (preg_match(
            '|^' . $this->config['root'] . '.*|',
            $req->getResourceUri()
        )) {
        
            // We just need the user
            $authToken = $req->headers('PHP_AUTH_USER');

            if (!($authToken && $this->verify($authToken))) {
                $res->status(401);
                $res->header(
                    'WWW-Authenticate',
                    sprintf('Basic realm="%s"', $this->config['realm'])
                );
            }

        }
        
        $this->next->call();
    }
    
    /**
     * Check passed auth token
     *
     * @param string $authToken
     * @return boolean
     */
    protected function verify($authToken)
    {
        $user = \ORM::forTable('users')->where('apikey', $authToken)
            ->findOne();

        if (false !== $user) {
            $this->app->user = $user->asArray();
            return true;
        }
        
        return false;
    }
}
