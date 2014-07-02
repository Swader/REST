<?php
require_once dirname(__FILE__) . '/../bootstrap.php';

use API\Middleware\TokenOverBasicAuth;
use API\Exception;
use API\Exception\ValidationException;

// General API group
$app->group(
    '/api',
    function () use ($app, $log) {

        // Common to all sub routes

        // Get contacts
        $app->get('/', function () {
            echo "<h1>This can be the documentation entry point</h1>";
            echo "<p>This URL could also contain discovery"
            ." information in side the headers</p>";
        });

        // Group for API Version 1
        $app->group(
            '/v1',
            // API Methods
            function () use ($app, $log) {

                // Get contacts
                $app->get(
                    '/contacts',
                    function () use ($app, $log) {

                        $contacts = array();
                        $filters = array();
                        $total = 0;
                        $pages = 1;

                        // Default resultset
                        $results = \ORM::forTable('contacts');

                        // Get and sanitize filters from the URL
                        if ($rawfilters = $app->request->get()) {
                            unset(
                                $rawfilters['sort'],
                                $rawfilters['fields'],
                                $rawfilters['page'],
                                $rawfilters['per_page']
                            );
                            foreach ($rawfilters as $key => $value) {
                                $filters[$key] = filter_var(
                                    $value,
                                    FILTER_SANITIZE_STRING
                                );
                            }

                        }

                        // Add filters to the query
                        if (!empty($filters)) {
                            foreach ($filters as $key => $value) {
                                if ('q' == $key) {
                                    $results->whereRaw(
                                        '(`firstname` LIKE ? OR `email` LIKE ?)',
                                        array('%'.$value.'%', '%'.$value.'%')
                                    );
                                } else {
                                    $results->where($key, $value);
                                }
                            }

                        }

                        // Get and sanitize field list from the URL
                        if ($fields = $app->request->get('fields')) {
                            $fields = explode(',', $fields);
                            $fields = array_map(
                                function ($field) {
                                    $field = filter_var(
                                        $field,
                                        FILTER_SANITIZE_STRING
                                    );
                                    return trim($field);
                                },
                                $fields
                            );
                        }

                        // Add field list to the query
                        if (is_array($fields) && !empty($fields)) {
                            $results->selectMany($fields);
                        }


                        // Manage sort options
                        // sort=firstname => ORDER BY firstname ASC
                        // sort=-firstname => ORDER BY firstname DESC
                        // sort=-firstname,email =>
                        // ORDER BY firstname DESC, email ASC
                        if ($sort = $app->request->get('sort')) {
                            $sort = explode(',', $sort);
                            $sort = array_map(
                                function ($s) {
                                    $s = filter_var($s, FILTER_SANITIZE_STRING);
                                    return trim($s);
                                },
                                $sort
                            );
                            foreach ($sort as $expr) {
                                if ('-' == substr($expr, 0, 1)) {
                                    $results->orderByDesc(substr($expr, 1));
                                } else {
                                    $results->orderByAsc($expr);
                                }
                            }
                        }


                        // Manage pagination
                        $page = filter_var(
                            $app->request->get('page'),
                            FILTER_SANITIZE_NUMBER_INT
                        );
                        if (!empty($page)) {

                            $perPage = filter_var(
                                $app->request->get('per_page'),
                                FILTER_SANITIZE_NUMBER_INT
                            );
                            if (empty($perPage)) {
                                $perPage = 10;
                            }

                            // Total after filters and
                            // before pagination limit
                            $total = $results->count();

                            // Compute the pagination Link header
                            $pages = ceil($total / $perPage);

                            // Base for all links
                            $linkBaseURL = $app->request->getUrl()
                                . $app->request->getRootUri()
                                . $app->request->getResourceUri();

                            // Init empty vars
                            $queryString = array();
                            $links = array();
                            $next =  '';
                            $last = '';
                            $prev =  '';
                            $first = '';

                            // Adding fields
                            if (!empty($fields)) {
                                $queryString[] = 'fields='
                                    . join(
                                        ',',
                                        array_map(
                                            function ($f) {
                                                return urlencode($f);
                                            },
                                            $fields
                                        )
                                    );
                            }

                            // Adding filters
                            if (!empty($filters)) {
                                $queryString[] = http_build_query($filters);
                            }

                            // Adding sort options
                            if (!empty($sort)) {
                                $queryString[] = 'sort='
                                    . join(
                                        ',',
                                        array_map(
                                            function ($s) {
                                                return urlencode($s);
                                            },
                                            $sort
                                        )
                                    );
                            }

                            // Next and Last link
                            if ($page < $pages) {
                                $next = $linkBaseURL . '?' . join(
                                    '&',
                                    array_merge(
                                        $queryString,
                                        array(
                                            'page=' . (string) ($page + 1),
                                            'per_page=' . $perPage
                                        )
                                    )
                                );
                                $last = $linkBaseURL . '?' . join(
                                    '&',
                                    array_merge(
                                        $queryString,
                                        array(
                                            'page=' . (string) $pages,
                                            'per_page=' . $perPage
                                        )
                                    )
                                );

                                $links[] = sprintf('<%s>; rel="next"', $next);
                                $links[] = sprintf('<%s>; rel="last"', $last);
                            }

                            // Previous and First link
                            if ($page > 1 && $page <= $pages) {
                                $prev = $linkBaseURL . '?' . join(
                                    '&',
                                    array_merge(
                                        $queryString,
                                        array(
                                            'page=' . (string) ($page - 1),
                                            'per_page=' . $perPage
                                        )
                                    )
                                );
                                $first = $linkBaseURL . '?' . join(
                                    '&',
                                    array_merge(
                                        $queryString,
                                        array(
                                            'page=1', 'per_page=' . $perPage
                                        )
                                    )
                                );
                                $links[] = sprintf('<%s>; rel="prev"', $prev);
                                $links[] = sprintf('<%s>; rel="first"', $first);
                            }

                            // Set header if required
                            if (!empty($links)) {
                                $app->response->headers->set(
                                    'Link',
                                    join(',', $links)
                                );
                            }

                            $results->limit($perPage)
                                ->offset($page * $perPage - $perPage);
                        }


                        $contacts = $results->findArray();

                        if (empty($total)) {
                            $total = count($contacts);
                        }
                        $app->response->headers->set('X-Total-Count', $total);

                        echo json_encode($contacts, JSON_PRETTY_PRINT);
                    }
                );

                // Get contact with ID
                $app->get(
                    '/contacts/:id',
                    function ($id) use ($app, $log) {

                        $id = filter_var(
                            filter_var($id, FILTER_SANITIZE_NUMBER_INT),
                            FILTER_VALIDATE_INT
                        );

                        if (false === $id) {
                            throw new ValidationException("Invalid contact ID");
                        }

                        $contact = \ORM::forTable('contacts')->findOne($id);
                        if ($contact) {

                            $output = $contact->asArray();

                            if ('notes' === $app->request->get('embed')) {
                                $notes = \ORM::forTable('notes')
                                    ->where('contact_id', $id)
                                    ->orderByDesc('id')
                                    ->findArray();

                                if (!empty($notes)) {
                                    $output['notes'] = $notes;
                                }
                            }

                            echo json_encode($output, JSON_PRETTY_PRINT);
                            return;
                        }
                        $app->notFound();
                    }
                );

                // Adds new contact
                $app->post(
                    '/contacts',
                    function () use ($app, $log) {

                        $body = $app->request()->getBody();

                        $errors = $app->validateContact($body);

                        if (empty($errors)) {
                            $contact = \ORM::for_table('contacts')->create();

                            if (isset($body['notes'])) {
                                $notes = $body['notes'];
                                unset($body['notes']);
                            }

                            $contact->set($body);

                            if (true === $contact->save()) {

                                // Insert notes
                                if (!empty($notes)) {
                                    $contactNotes = array();
                                    foreach ($notes as $item) {
                                        $item['contact_id'] = $contact->id;
                                        $note = \ORM::for_table('notes')
                                            ->create();
                                        $note->set($item);
                                        if (true === $note->save()) {
                                            $contactNotes[] = $note->asArray();
                                        }
                                    }
                                }

                                $output = $contact->asArray();
                                if (!empty($contactNotes)) {
                                    $output['notes'] = $contactNotes;
                                }
                                echo json_encode($output, JSON_PRETTY_PRINT);
                            } else {
                                throw new Exception("Unable to save contact");
                            }

                        } else {
                            throw new ValidationException(
                                "Invalid data",
                                0,
                                $errors
                            );
                        }
                    }
                );

                // Update contact with ID
                $app->map(
                    '/contacts/:id',
                    function ($id) use ($app, $log) {

                        $contact = \ORM::forTable('contacts')->findOne($id);

                        if ($contact) {

                            $body = $app->request()->getBody();

                            $errors = $app->validateContact($body, 'update');

                            if (empty($errors)) {

                                if (isset($body['notes'])) {
                                    $notes = $body['notes'];
                                    unset($body['notes']);
                                }

                                $contact->set($body);

                                if (true === $contact->save()) {

                                    // Process notes
                                    if (!empty($notes)) {
                                        $contactNotes = array();
                                        foreach ($notes as $item) {

                                            $item['contact_id'] = $contact->id;

                                            if (empty($item['id'])) {

                                                // New note
                                                $note = \ORM::for_table('notes')
                                                    ->create();
                                            } else {

                                                // Existing note
                                                $note = \ORM::forTable('notes')
                                                    ->findOne($item['id']);
                                            }

                                            if ($note) {
                                                $note->set($item);
                                                if (true === $note->save()) {
                                                    $contactNotes[] = $note->asArray();
                                                }
                                            }
                                        }
                                    }

                                    $output = $contact->asArray();
                                    if (!empty($contactNotes)) {
                                        $output['notes'] = $contactNotes;
                                    }
                                    echo json_encode(
                                        $output,
                                        JSON_PRETTY_PRINT
                                    );
                                    return;

                                } else {
                                    throw new Exception(
                                        "Unable to save contact"
                                    );
                                }

                            } else {
                                throw new ValidationException(
                                    "Invalid data",
                                    0,
                                    $errors
                                );
                            }

                        }

                        $app->notFound();
                    }
                )->via('PUT', 'PATCH');


                // Delete contact with ID
                $app->delete(
                    '/contacts/:id',
                    function ($id) use ($app, $log) {

                        $contact = \ORM::forTable('contacts')->findOne($id);

                        if ($contact) {

                            $contact->delete();

                            $app->halt(204);
                        }

                        $app->notFound();
                    }
                );


                // Add contact to favorites
                $app->put(
                    '/contacts/:id/star',
                    function ($id) use ($app, $log) {
                        
                        $contact = \ORM::forTable('contacts')->findOne($id);

                        if ($contact) {
                            $contact->set('favorite', 1);
                            if (true === $contact->save()) {
                                $output = $contact->asArray();
                                echo json_encode(
                                    $output,
                                    JSON_PRETTY_PRINT
                                );
                                return;
                            } else {
                                throw new Exception(
                                    "Unable to save contact"
                                );
                            }
                        }

                        $app->notFound();
                    }
                );

                // Remove contact from favorites
                $app->delete(
                    '/contacts/:id/star',
                    function ($id) use ($app, $log) {
                        $contact = \ORM::forTable('contacts')->findOne($id);

                        if ($contact) {
                            $contact->set('favorite', 0);
                            if (true === $contact->save()) {
                                $output = $contact->asArray();
                                echo json_encode(
                                    $output,
                                    JSON_PRETTY_PRINT
                                );
                                return;
                            } else {
                                throw new Exception(
                                    "Unable to save contact"
                                );
                            }
                        }
                        $app->notFound();
                    }
                );

                // Get notes for contact
                $app->get(
                    '/contacts/:id/notes',
                    function ($id) use ($app, $log) {
                        
                        $contact = \ORM::forTable('contacts')
                            ->select('id')->findOne($id);

                        if ($contact) {
                            $notes = \ORM::forTable('notes')
                                ->where('contact_id', $id)->findArray();
                            echo json_encode($notes, JSON_PRETTY_PRINT);
                            return;
                        }

                        $app->notFound();
                    }
                );

                // Add a new note for contact with id :id
                $app->post(
                    '/contacts/:id/notes',
                    function ($id) use ($app, $log) {

                        $contact = \ORM::forTable('contacts')
                            ->select('id')->findOne($id);

                        if ($contact) {

                            $body = $app->request()->getBody();

                            $errors = $app->validateNote($body);
                            
                            if (empty($errors)) {
                                
                                $note = \ORM::for_table('notes')
                                    ->create();

                                $note->set($body);
                                $note->contact_id = $id;
                                
                                if (true === $note->save()) {
                                    
                                    echo json_encode(
                                        $note->asArray(),
                                        JSON_PRETTY_PRINT
                                    );
                                    return;
                                } else {
                                    throw new Exception(
                                        "Unable to save note"
                                    );
                                }
                                
                            } else {
                                throw new ValidationException(
                                    "Invalid data",
                                    0,
                                    $errors
                                );
                            }

                        }
                        $app->notFound();
                    }
                );

                // Get single note
                $app->get(
                    '/contacts/:id/notes/:note_id',
                    function ($id, $note_id) use ($app, $log) {

                        $id = filter_var(
                            filter_var($id, FILTER_SANITIZE_NUMBER_INT),
                            FILTER_VALIDATE_INT
                        );

                        if (false === $id) {
                            throw new ValidationException("Invalid contact ID");
                        }

                        $note_id = filter_var(
                            filter_var($note_id, FILTER_SANITIZE_NUMBER_INT),
                            FILTER_VALIDATE_INT
                        );

                        if (false === $note_id) {
                            throw new ValidationException("Invalid note ID");
                        }

                        $contact = \ORM::forTable('contacts')
                            ->select('id')->findOne($id);

                        if ($contact) {

                            $note = \ORM::forTable('notes')->findOne($note_id);
                            
                            if ($note) {

                                echo json_encode(
                                    $note->asArray(),
                                    JSON_PRETTY_PRINT
                                );
                                return;
                            }

                        }
                        $app->notFound();
                    }
                );

                // Update a single note
                $app->map(
                    '/contacts/:id/notes/:note_id',
                    function ($id, $note_id) use ($app, $log) {

                        $id = filter_var(
                            filter_var($id, FILTER_SANITIZE_NUMBER_INT),
                            FILTER_VALIDATE_INT
                        );

                        if (false === $id) {
                            throw new ValidationException("Invalid contact ID");
                        }

                        $note_id = filter_var(
                            filter_var($note_id, FILTER_SANITIZE_NUMBER_INT),
                            FILTER_VALIDATE_INT
                        );

                        if (false === $note_id) {
                            throw new ValidationException("Invalid note ID");
                        }

                        $contact = \ORM::forTable('contacts')
                            ->select('id')->findOne($id);
                        
                        if ($contact) {
                            
                            $note = \ORM::forTable('notes')->findOne($note_id);
                            
                            if ($note) {

                                $body = $app->request()->getBody();

                                $errors = $app->validateNote($body, 'update');
                                
                                if (empty($errors)) {
                                    $note->set('body', $body['body']);
                                    if (true === $note->save()) {
                                        
                                        echo json_encode(
                                            $note->asArray(),
                                            JSON_PRETTY_PRINT
                                        );
                                        return;

                                    } else {
                                        
                                        throw new Exception(
                                            "Unable to save note"
                                        );
                                    }
                                } else {
                                    throw new ValidationException(
                                        "Invalid data",
                                        0,
                                        $errors
                                    );

                                }

                            }
                        }
                        $app->notFound();
                    }
                )->via('PUT', 'PATCH');

                // Delete single note
                $app->delete(
                    '/contacts/:id/notes/:note_id',
                    function ($id, $note_id) use ($app, $log) {

                        $contact = \ORM::forTable('contacts')
                            ->select('id')->findOne($id);

                        if ($contact) {

                            $note = \ORM::forTable('notes')->findOne($note_id);
                            
                            if ($note) {
                                $note->delete();
                                $app->halt(204);
                            }

                        }

                        $app->notFound();
                    }
                );

            }
        );
    }
);

// Public human readable home page
$app->get(
    '/',
    function () use ($app, $log) {
        echo "<h1>Hello, this can be the public App Interface</h1>";
    }
);

// JSON friendly errors
// NOTE: debug must be false
// or default error template will be printed
$app->error(function (\Exception $e) use ($app, $log) {

    $mediaType = $app->request->getMediaType();

    $isAPI = (bool) preg_match('|^/api/v.*$|', $app->request->getPath());

    // Standard exception data
    $error = array(
        'code' => $e->getCode(),
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    );

    // Graceful error data for production mode
    if (!in_array(
        get_class($e),
        array('API\\Exception', 'API\\Exception\ValidationException')
    )
        && 'production' === $app->config('mode')) {
        $error['message'] = 'There was an internal error';
        unset($error['file'], $error['line']);
    }

    // Custom error data (e.g. Validations)
    if (method_exists($e, 'getData')) {
        $errors = $e->getData();
    }

    if (!empty($errors)) {
        $error['errors'] = $errors;
    }

    $log->error($e->getMessage());
    if ('application/json' === $mediaType || true === $isAPI) {
        $app->response->headers->set(
            'Content-Type',
            'application/json'
        );
        echo json_encode($error, JSON_PRETTY_PRINT);
    } else {
        echo '<html>
        <head><title>Error</title></head>
        <body><h1>Error: ' . $error['code'] . '</h1><p>'
        . $error['message']
        .'</p></body></html>';
    }

});

/// Custom 404 error
$app->notFound(function () use ($app) {

    $mediaType = $app->request->getMediaType();

    $isAPI = (bool) preg_match('|^/api/v.*$|', $app->request->getPath());


    if ('application/json' === $mediaType || true === $isAPI) {

        $app->response->headers->set(
            'Content-Type',
            'application/json'
        );

        echo json_encode(
            array(
                'code' => 404,
                'message' => 'Not found'
            ),
            JSON_PRETTY_PRINT
        );

    } else {
        echo '<html>
        <head><title>404 Page Not Found</title></head>
        <body><h1>404 Page Not Found</h1><p>The page you are
        looking for could not be found.</p></body></html>';
    }
});

$app->run();
