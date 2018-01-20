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
     * Keeps nval
     * 
     * @var array
     */
    protected $nval     = [];

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
    protected $config;
    protected $name;
    protected $viewName;
    protected $edit;
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
            throw new InvalidArgumentException(NULL, '4. ');

        if( is_array($this->_methodType($name, $met)) )
            return $this->_multipleRules($name, $config, $viewName, $met);

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
        $edit     = $this->_methodType($name, $met);

        if( ! isset($edit) ) return false;

        if( in_array('trim',$config) ) $edit = trim($edit);

        if( in_array('nc', $config) )
        {
            $secnc = Security\Properties::$ncEncode;
            $edit  = Security\NastyCode::encode($edit, $secnc['badChars'], $secnc['changeBadChars']);
        }

        if( in_array('html',      $config) ) $edit = Security\Html::encode($edit);
        if( in_array('xss',       $config) ) $edit = Security\CrossSiteScripting::encode($edit);
        if( in_array('injection', $config) ) $edit = Security\Injection::encode($edit);
        if( in_array('script',    $config) ) $edit = Security\Script::encode($edit);
        if( in_array('php',       $config) ) $edit = Security\PHP::encode($edit);

        $this->nval[$name] = $edit;

        $this->_methodNval($name, $edit, $met);

        $this->config   = $config;
        $this->edit     = $edit;
        $this->name     = $name;
        $this->viewName = $viewName;
        $this->method   = $met;

        $this->_singleType   (['matchPassword' => 'passwordMatch']);
        $this->_singleType   (['match'         => 'dataMatch']);
        $this->_singleInArray('numeric');
        $this->_singleInArray('phone');
        $this->_singleInArray('alpha');
        $this->_singleInArray('alnum');
        $this->_singleInArray('email');
        $this->_singleInArray('url');
        $this->_singleInArray('identity');
        $this->_singleInArray(['specialChar' => 'noSpecialChar']);
        $this->_between      ('between');
        $this->_between      ('betweenBoth');
        $this->_minmax       ('minchar');
        $this->_minmax       ('maxchar');
        $this->_required     ();
        $this->_captcha      ();
        $this->_oldPassword  ();
        $this->_phone        ();
        $this->_pattern      ();

        array_push($this->errors, $this->messages);

        $this->_defaultVariables();
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
     * It keeps the last value of the passed data from the filter.
     * 
     * @param string $name
     * 
     * @return string
     */
    public function nval(String $name)
    {
        if( isset($this->nval[$name]) )
        {
            return $this->nval[$name];
        }
        else
        {
            return false;
        }
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
        $method = $this->_methodType($name, $met);

        if( ! isset($method) )
        {
            return false;
        }

        return $method;
    }

    /**
     * protected pattern
     * 
     * @param void
     * 
     * @return void
     */
    protected function _pattern()
    {
        if( isset($this->config['pattern']) )
        {
            if( ! preg_match($this->config['pattern'], $this->edit) )
            {
                $this->_messages('pattern', $this->name, $this->viewName);
            }
        }
    }

    /**
     * protected minmax
     * 
     * @param string $type = 'minchar'
     * 
     * @return void
     */
    protected function _minmax($type = 'minchar')
    {
        if( isset($this->config[$type]) )
        {
            if( ! Validator::$type($this->edit, $this->config[$type]) )
            {
                $this->_messages($type, $this->name, ["%" => $this->viewName, "#" => $this->config[$type]]);
            }
        }
    }

    /**
     * protected between
     * 
     * @param string $type = 'between'
     * 
     * @return void
     */
    protected function _between($type = 'between')
    {
        if( $between = ($this->config[$type] ?? NULL) )
        {
            if( ! Validator::$type($this->edit, $betweenMin = $between[0], $betweenMax = $between[1] ?? 0) )
            {
                $this->_messages($type, $this->name, ['%' => $this->viewName, '#' => $betweenMin, '$' => $betweenMax]);
            }
        }
    }

    /**
     * protected phone
     * 
     * @param void
     * 
     * @return void
     */
    protected function _phone()
    {
        if( isset($config['phone']) )
        {
            if( ! Validator::phone($this->edit, $this->config['phone']) )
            {
                $this->_messages('phone', $this->name, $this->viewName);
            }
        }
    }

    /**
     * protected old password
     * 
     * @param void
     * 
     * @return void
     */
    protected function _oldPassword()
    {
        if( isset($config['oldPassword']) )
        {
            $pm = '';
            $pm = $this->config['oldPassword'];

            if( Encode\SuperAlgorithm::create($this->edit) != $pm )
            {
                $this->_messages('oldPasswordMatch', $this->name, $this->viewName);
            }
        }
    }

    /**
     * protected captcha
     * 
     * @param void
     * 
     * @return void
     */
    protected function _captcha()
    {
        if( in_array('captcha', $this->config) )
        {
            if( $this->edit !== Singleton::class('ZN\Captcha\Render')->getCode() )
            {
                $this->_messages('captchaCode', $this->name, $this->viewName);
            }
        }
    }

    /**
     * protected required
     * 
     * @param void
     * 
     * @return void
     */
    protected function _required()
    {
        if( in_array('required', $this->config) )
        {
            if( empty($this->edit) )
            {
                $this->_messages('required', $this->name, $this->viewName);
            }
        }
    }

    /**
     * protected single type
     * 
     * @param mixed $type
     * 
     * @return void
     */
    protected function _singleType($type)
    {
        $typeName = $typeMsg = $type;

        if( is_array($type) )
        {
            $typeName = key($type);
            $typeMsg  = current($type);
        }

        if( isset($this->config[$typeName]) )
        {
            $pm = $this->_methodType($this->config[$typeName], $this->method);

            if( $this->edit != $pm )
            {
                $this->_messages($typeMsg, $this->name, $this->viewName);
            }
        }
    }

    /**
     * protected single in array
     * 
     * @param mixed $type
     * 
     * @return void
     */
    protected function _singleInArray($type)
    {
        $typeName = $typeMsg = $type;

        if( is_array($type) )
        {
            $typeName = key($type);
            $typeMsg  = current($type);
        }

        if( in_array($typeName, $this->config) )
        {
            if( ! Validator::$typeName($this->edit) )
            {
                $this->_messages($typeMsg, $this->name, $this->viewName);
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
    protected function _messages($type, $name, $viewName)
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
    protected function _defaultVariables()
    {
        $this->messages = [];
        $this->index    = 0;
        $this->config   = NULL;
        $this->edit     = NULL;
        $this->name     = NULL;
        $this->viewName = NULL;
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
    protected function _methodType($name, $met)
    {
        if( $met === "data" )
        {
            return $name;
        }

        return Method::$met($name);
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
    protected function _methodNval($name, $val, $met)
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
    protected function _multipleRules(String $name, Array $config = [], $viewName = '', String $met = 'post')
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
