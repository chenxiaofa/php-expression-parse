<?php
/**
 * Created by PhpStorm.
 * User: xfachen
 * Date: 2017/4/21
 * Time: 15:07
 */

namespace anong\expression;


class SqlExpression extends Expression
{
    public static $validFunction = ['FROM_UNIXTIME'];
    public static $checkFunction = false;
    public static $is_pretreatment = false;
    public function parseToken()
    {
        parent::parseToken();

        foreach($this->tokens as $token)
        {
            /** @var Token $token */
            if ($token->type === Token::TYPE_OPERATOR && ($token->rawToken == '==' || $token->rawToken == '==='))
            {
                $token->rawToken = $token->token = '=';
            }
        }

    }



}