<?php
/**
 * Created by PhpStorm.
 * User: xfachen
 * Date: 2017/1/12
 * Time: 9:53
 */

namespace anong\expression;

use \Exception;

class Token
{
    const TYPE_OPERATOR = 0;
    const TYPE_VARIABLE = 1;
    const TYPE_CONST = 2;
    const TYPE_SUB_EXPRESSION = 3;
    const TYPE_FUNCTION_CALL = 4;

    /** @var string|static  */
    public $token = '';
    public $type = 1;
    public $functionName = '';
    public $functionParams = [];
    public $rawToken = '';
    public $monadicOp = null;


    /** for type const */
    public $flag = 0;
    const FLAG_NUMERIC      = 0;
    const FLAG_STRING   = 1;
    const FLAG_IGNORE_FUNCTION_CHECK = 2;

    public function __construct($token,$type,$flag = 0)
    {
        $this->rawToken = $token;
        $this->token = $token;
        $this->type = $type;
        $this->flag = $flag;
        if ($type === self::TYPE_SUB_EXPRESSION)
        {
            $this->token = Expression::get($token);
        }
        elseif ($type === self::TYPE_FUNCTION_CALL)
        {
            $firstCallPos = strpos($token,'(');
            $this->functionName = substr($token,0,$firstCallPos);

            $params = [];
            $paramString = substr($token,$firstCallPos+1,strlen($token)-$firstCallPos-2);
            if ($paramString)
            {
                $params = explode(',',$paramString);
            }
            $paramCount = count($params);
            if ($this->flag !== self::FLAG_IGNORE_FUNCTION_CHECK)
            {
                $paramInfo = $this->getFunctionParamsInfo($this->functionName);
                if ($paramCount < $paramInfo['min'])
                {
                    throw new Exception($this->functionName.'() expects at least '.$paramInfo['min'].' parameter, '.$paramCount.' given');
                }
                if ($paramInfo['max'] > -1 && $paramInfo['max'] < $paramCount)
                {
                    throw new Exception($this->functionName.'() expects exactly '.$paramInfo['max'].' parameter, '.$paramCount.' given');
                }
            }
            foreach($params as $i=>$param)
            {
                if ($param === '')
                {
                    throw new Exception('parse error,function parameter '.($i+1).' is missing');
                }
                $this->functionParams[] = self::createSubExpressionToken($param);
            }
        }

    }


    public function getToken()
    {
        $flag = '';
        if (!is_null($this->monadicOp))
        {
            $flag = $this->monadicOp;
        }
        if ($this->type === self::TYPE_SUB_EXPRESSION)
        {
            $exp = $this->token->getCompiledExpression();
            if ($flag)
            {
                return $flag.'('.trim($exp,'()').')';
            }
            return $exp;
        }elseif ($this->type === self::TYPE_CONST)
        {
            if ($this->flag === self::FLAG_STRING)
            {
                return $flag.'"'.str_replace("\"","\\\"",$this->token).'"';
            }
        }
        return $flag.$this->token;
    }

    public static function createSubExpressionToken($expression)
    {
        return new Token($expression,self::TYPE_SUB_EXPRESSION);
    }

    /**
     * @param array $variables
     * @return int|mixed
     */
    public function calc($variables = [])
    {
        $result = $this->_calc($variables);
        if (!is_null($this->monadicOp))
        {
            switch ($this->monadicOp)
            {
                case '-':
                    return 0-$result;
                case '!':
                    return !$result;
                case '+':
                    return 0+$result;
            }
        }
        return $result;
    }

    /**
     * 计算
     * @param array $variables
     * @return int|mixed
     * @throws Exception
     */
    public function _calc($variables = [])
    {
        switch($this->type)
        {
            case self::TYPE_OPERATOR:
                return 0;//todo
            case self::TYPE_VARIABLE:
                if (array_key_exists($this->token,$variables))
                {
                    return $variables[$this->token];
                }
                throw new \Exception('miss variable:'.$this->token);
                break;
            case self::TYPE_CONST:
                if ($this->flag === self::FLAG_NUMERIC)
                {
                    return 0+$this->token;
                }
                return $this->token;
            case self::TYPE_SUB_EXPRESSION:
                return $this->token->calc($variables);
            case self::TYPE_FUNCTION_CALL:
                $params = [];
                /** @var static $paramToken */
                foreach($this->functionParams as $paramToken)
                {
                    $params[] = $paramToken->calc($variables);
                }
                return call_user_func_array($this->functionName,$params);

        }
    }

    public function getVariables()
    {
        switch($this->type)
        {
            case self::TYPE_SUB_EXPRESSION:
                return $this->token->getVariables();
            case self::TYPE_VARIABLE:
                return [$this->rawToken];
            case self::TYPE_FUNCTION_CALL;
                $output = [];
                /** @var Token $paramToken */
                foreach($this->functionParams as $paramToken)
                {
                    $output = array_merge($output,$paramToken->getVariables());
                }
                return array_unique($output);
        }
        return [];
    }

    public function getFunctionParamsInfo($functionName)
    {
        static $cache = [];
        if (!array_key_exists($functionName,$cache))
        {
            $refFunc = new \ReflectionFunction($functionName);
            $params = $refFunc->getParameters();
            $max = 0;
            $min = 0;
            /** @var \ReflectionParameter $param */
            foreach($params as $param)
            {
                $max++;
                if (!$param->isOptional())
                {
                    $min++;
                }
                if ($param->getName() == '...')
                {
                    $max = -1;
                }
            }
            $cache[$functionName] = [
                'min'=>$min,
                'max'=>$max,
                'help'=>$refFunc.''
            ];
        }
        return $cache[$functionName];
    }



}