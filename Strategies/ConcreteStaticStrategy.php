<?php

/**
 * Codeburner Framework.
 *
 * @author Alex Rohleder <contato@alexrohleder.com.br>
 * @copyright 2015 Alex Rohleder
 * @license http://opensource.org/licenses/MIT
 */

namespace Codeburner\Router\Strategies;

/**
 * Codeburner Router Component.
 *
 * @author Alex Rohleder <contato@alexrohleder.com.br>
 * @see https://github.com/codeburnerframework/router
 */
class ConcreteStaticStrategy implements \Codeburner\Router\DispatcherStrategyInterface
{

    /**
     * Dispatche the matched route action.
     *
     * @param  string|array|closure $action The matched route action.
     * @param  array                $params The route parameters.
     *
     * @return mixed The response of request.
     */
    public function dispatch($action, array $params)
    {
        call_user_func_array($action, $params);
    }

}
