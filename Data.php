<?php namespace ZN\Validation;
/**
 * ZN PHP Web Framework
 * 
 * "Simplicity is the ultimate sophistication." ~ Da Vinci
 * 
 * @package ZN
 * @license MIT [http://opensource.org/licenses/MIT]
 * @author  Ozan UYKUN [ozan@znframework.com]
 */

use ZN\Lang;
use ZN\Security;
use ZN\Singleton;
use ZN\Request\Method;
use ZN\DataTypes\Arrays;
use ZN\Cryptography\Encode;
use ZN\Validation\Exception\InvalidArgumentException;

class Data implements DataInterface
{
    use RulesPropertiesTrait;

    /**
     * Keeps options.
     * 
     * @var array
     */
    protected $options   = ['post', 'get', 'request', 'data'];

    /**
     * Keeps errors
     * 
     * @var array
     */
    protected $errors   = [];

    /**
     * Keeps error
     * 
     * @var array
     */
    protected $error    = [];

    /**
     * Keeps messages
     * 
     * @var array
     */
    protected $messages = [];

    /**
     * Keeps index
     * 
     * @var int
     */
    protected $index = 0;

    /**
     * @var string
     */
    protected $method;

   /**
     * Defines rules for control of the grant.
     * 
     * @param string $name
     * @param array  $config   = []
     * @param string $viewName = ''
     * @param string $met      = 'post' - options[post|get]
     * 
     * @return void
     */
    public function rules(String $name, Array $config = [], $viewName = '', String $met = 'post')
    {
        if( ! in_array($met, $this->options) )
        {
            throw new InvalidArgumentException(NULL, '4. ');
        }    

        if( is_array($this->setMethodType($name, $met)) )
        {
            return $this->setMultipleRules($name, $config, $viewName, $met);
        }      

        $met      = $this->settings['method'] ?? 'post';
        $viewName = $this->settings['value']  ?? $viewName;

        $config = array_merge
        (
            $config,
            $this->settings['config']   ?? [],
            $this->settings['validate'] ?? [],
            $this->settings['secure']   ?? [],
            $this->settings['pattern']  ?? []
        );

        $this->settings = [];

        $viewName = $viewName ?: $name;
        $edit     = $this->setMethodType($name, $met);

        if( ! isset($edit) ) return false;

        $this->method = $met;

        foreach( $config as $key => $val )
        {
            $function = is_numeric($key) ? $val : $key;

            if( $this->isValidatorObject($function) )
            {
                if( is_numeric($key) )
                {
                    if( is_string($cleanData = Validator::$function($edit)) )
                    {
                        $edit = $cleanData;
                    }
                    
                    $this->validInArray($function, $edit, $name, $viewName);
                }
                else
                {
                    $this->validIssetArray($function, $edit, $val, $name, $viewName);
                }
            }    
        }

        $this->setMethodNewValue($name, $edit, $met);

        array_push($this->errors, $this->messages);

        $this->defaultVariables();
    }

    /**
     * It checks the data.
     * 
     * @param string $submit = NULL
     * 
     * @return bool
     */
    public function check(String $submit = NULL) : Bool
    {
        $session = Singleton::class('ZN\Storage\Session');

        $method = $session->FormValidationMethod() ?: 'post';

        if( $submit !== NULL && ! $method::$submit() ) 
        {
            return false;
        }
        
        $rules = $session->FormValidationRules();

        if( is_array($rules) )
        {
            $session->delete('FormValidationRules');
            $session->delete('FormValidationMethod');

            foreach( $rules as $name => $rule )
            {
                $value = $rule['value'] ?? $name;
                
                unset($rule['value']);

                $rule = Arrays\Unidimensional::do($rule);
         
                $this->rules($name, $rule, $value, $method);
            } 
        }

        return ! (Bool) $this->error('string');
    }

    /**
     * Get error
     * 
     * @param string $name = 'array' - options[array|string]
     */
    public function error(String $name = 'array')
    {
        if( $name === "string" || $name === "array" || $name === "echo" )
        {
            if( count($this->errors) > 0 )
            {
                $result = '';
                $resultArray = [];

                foreach( $this->errors as $key => $value )
                {
                    if( is_array($value) )foreach($value as $k => $val)
                    {
                        $result .= $val;
                        $resultArray[] = str_replace("<br>", '', $val);
                    }
                }

                if( $name === "string" || $name === "echo" )
                {
                    return $result;
                }

                if( $name === "array")
                {
                    return $resultArray;
                }
            }
            else
            {
                return false;
            }
        }
        else
        {
            if( isset($this->error[$name]) )
            {
                return $this->error[$name];
            }
            else
            {
                return false;
            }
        }
    }

    /**
     * Get input post back.
     * 
     * @param string $name
     * @param string $met = 'post' - options[post|get]
     */
    public function postBack(String $name, String $met = 'post')
    {
        $method = $this->setMethodType($name, $met);

        if( ! isset($method) )
        {
            return false;
        }

        return $method;
    }

    /**
     * Protected is validator object
     */
    protected function isValidatorObject($object)
    {
        return method_exists('ZN\Validation\Validator', $object);
    }

    /**
     * protected single in array
     * 
     * @param mixed $key
     * 
     * @return void
     */
    protected function validInArray($key, $data, $name, $viewName)
    {
        if( ! Validator::$key($data) )
        {
            $this->setMessages($key, $name, $viewName);
        }
    }

    /**
     * protected single isset array
     * 
     * @param mixed $key
     * 
     * @return void
     */
    protected function validIssetArray($key, $data, $check, $name, $viewName)
    {
        $data = $this->setMethodType($name, $this->method);
     
        if( ! Validator::$key($data, ...(array) $check) )
        {
            if( in_array($key, ['minchar', 'maxchar']) )
            {
                $this->setMessages($key, $name, ["%" => $viewName, "#" => $check]);
            }
            elseif( in_array($key, ['between', 'betweenBoth']) )
            {
                $this->setMessages($key, $name, ['%' => $viewName, '#' => $check[0], '$' => $check[1] ?? 0]);
            }
            else
            {
                $this->setMessages($key, $name, $viewName);
            }
        }
    }

    /**
     * protected messages
     * 
     * @param string $type
     * @param string $name
     * @param string $viewName
     * 
     * @return void
     */
    protected function setMessages($type, $name, $viewName)
    {
        $message = Lang::select('ViewObjects', 'validation:'.$type, $viewName);

        $this->messages[$this->index] = $message.'<br>'; $this->index++;
        $this->error[$name]           = $message;
    }

    /**
     * Default variables
     * 
     * @param void
     * 
     * @return void
     */
    protected function defaultVariables()
    {
        $this->messages = [];
        $this->index    = 0;
        $this->method   = NULL;
    }

    /**
     * protected method type
     * 
     * @param string $name 
     * @param string $met
     * 
     * @return mixed
     */
    protected function setMethodType($name, $met)
    {
        if( $met === "data" )
        {
            return $name;
        }

        return Method::$met($name) ?: $name;
    }

    /**
     * protected method new value
     * 
     * @param string $name 
     * @param string $val
     * @param string $met
     * 
     * @return mixed
     */
    protected function setMethodNewValue($name, $val, $met)
    {
        if( $met === "data" )
        {
            return;
        }

        return Method::$met($name, $val);
    }

    /**
     * protected multiple rules
     * 
     * @param string $name 
     * @param array  $config   = []
     * @param string $viewName = ''
     * @param string $set      = 'post' - options[post|get]
     * 
     * @return void
     */
    protected function setMultipleRules(String $name, Array $config = [], $viewName = '', String $met = 'post')
    {
        $postNames = [];
        $postKey   = '';
        $postDatas = (array) Method::$met($name);

        foreach( $postDatas as $key => $postData )
        {
            $postName = $name . $key;

            Method::$met($postName, $postData);

            $postKey = is_array($viewName)
                     ? $viewName[$key] ?? $postName
                     : $postName;

            $this->rules($postName, $config, $postKey, $met);
        }
    }
}
