<?php
namespace API;

use Slim\Slim;

class Application extends Slim
{
    public function validateContact($contact = array(), $action = 'create')
    {
        $errors = array();
        
        if (!empty($contact['notes'])) {
            $notes = $contact['notes'];
            unset($contact['notes']);
        }

        $contact = filter_var_array(
            $contact,
            array(
                'id' => FILTER_SANITIZE_NUMBER_INT,
                'firstname' => FILTER_SANITIZE_STRING,
                'lastname' => FILTER_SANITIZE_STRING,
                'email' => FILTER_SANITIZE_EMAIL,
                'phone' => FILTER_SANITIZE_STRING,
            ),
            false
        );
        
        switch ($action) {
            
            case 'update':
                if (empty($contact['id'])) {
                    $errors['contact'][] = array(
                        'field' => 'id',
                        'message' => 'ID cannot be empty on update'
                    );
                    break;
                }
                if (isset($contact['firstname'])
                    && empty($contact['firstname'])) {
                    $errors['contact'][] = array(
                        'field' => 'firstname',
                        'message' => 'First name cannot be empty'
                    );
                }
                if (isset($contact['email'])) {
                    if (empty($contact['email'])) {
                        $errors['contact'][] = array(
                            'field' => 'email',
                            'message' => 'Email address cannot be empty'
                        );
                        break;
                    }
            
                    if (false === filter_var(
                        $contact['email'],
                        FILTER_VALIDATE_EMAIL
                    )) {
                        $errors['contact'][] = array(
                            'field' => 'email',
                            'message' => 'Email address is invalid'
                        );
                        break;
                    }
            
                    // Test for unique email
                    $results = \ORM::forTable('contacts')
                        ->where('email', $contact['email'])->findOne();
                    if (false !== $results
                        && $results->id !== $contact['id']) {
                        $errors['contact'][] = array(
                            'field' => 'email',
                            'message' => 'Email address already exists'
                        );
                    }
                }
                break;
            
            case 'create':
            default:
                if (empty($contact['firstname'])) {
                    $errors['contact'][] = array(
                        'field' => 'firstname',
                        'message' => 'First name cannot be empty'
                    );
                }
                if (empty($contact['email'])) {
                    $errors['contact'][] = array(
                        'field' => 'email',
                        'message' => 'Email address cannot be empty'
                    );
                } elseif (false === filter_var(
                    $contact['email'],
                    FILTER_VALIDATE_EMAIL
                )) {
                        $errors['contact'][] = array(
                            'field' => 'email',
                            'message' => 'Email address is invalid'
                        );
                } else {
                
                    // Test for unique email
                    $results = \ORM::forTable('contacts')
                        ->where('email', $contact['email'])->count();
                    if ($results > 0) {
                        $errors['contact'][] = array(
                            'field' => 'email',
                            'message' => 'Email address already exists'
                        );
                    }
                }
                
                break;
        }
        

        if (!empty($notes) && is_array($notes)) {
            $noteCount = count($notes);
            for ($i = 0; $i < $noteCount; $i++) {
                
                $noteErrors = $this->validateNote($notes[$i], $action);
                if (!empty($noteErrors)) {
                    $errors['notes'][] = $noteErrors;
                    unset($noteErrors);
                }

            }
        }

        return $errors;
    }
    
    public function validateNote($note = array(), $action = 'create')
    {
        $errors = array();

        $note = filter_var_array(
            $note,
            array(
                'id' => FILTER_SANITIZE_NUMBER_INT,
                'body' => FILTER_SANITIZE_STRING,
                'contact_id' => FILTER_SANITIZE_NUMBER_INT,
            ),
            false
        );
        
        if (isset($note['body']) && empty($note['body'])) {
            $errors[] = array(
                'field' => 'body',
                'message' => 'Note body cannot be empty'
            );
        }
        

        return $errors;
    }
}
