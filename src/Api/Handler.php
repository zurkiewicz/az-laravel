<?php

namespace AZ\Laravel\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Contracts\Routing\ResponseFactory;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * API Request Handler
 * 
 */
class Handler
{

    /**
     * 
     *
     * @var Request
     */
    private Request $request;

    /**
     * 
     * @param int $code
     * @param mixed $message
     * @return \Illuminate\Http\JsonResponse
     */
    public static function getError(int $code = 500, $message = 'Unknown'): mixed
    {

        return response()
            ->json([
                'error' => true,
                'code' => $code,
                'message' => $message,
            ], $code)
            ->header('Content-Type', 'application/vnd.api+json');
    }

    /**
     * __construct
     *
     * <code>
     * $handler = new Handler($request);<br>
     * return $handler->response(function (Request $request) {<br>
     *      return ['status' => 'OK', 'uri' => $request->getRequestUri()];<br>
     * });<br>
     * </code>
     *
     * @param Request $request
     */
    public function __construct(Request $request)
    {

        $this->request = $request;
    }


    /**
     *
     * @param callable $callback
     * @return mixed
     */
    protected function run($callback)
    {

        return \call_user_func_array($callback, [$this->request]);
    }


    /**
     * Create a safe response, including an exception handler.
     *
     * @param callable $callback
     * @return \Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory|Symfony\Component\HttpFoundation\Response
     */
    public function response($callback)
    {

        try {

            $result = $this->run($callback);

            if ($result instanceof Response || $result instanceof ResponseFactory || $result instanceof SymfonyResponse) {

                return $result;
            }

            if (\is_array($result)) {

                return response()
                    ->json($result)
                    ->header('Content-Type', 'application/vnd.api+json');
            }

            return response($result);

        } catch (\Throwable $th) {

            $code = $th->getCode();
            $code = $code ? $code : 500;

            if ($code <= 200) {

                $code = 500;
            }

            
            return static::getError($code, $th->getMessage());

            // return response()
            //     ->json([
            //         'error' => true,
            //         'code' => $code,
            //         'message' => $th->getMessage(),
            //     ], $code)
            //     ->header('Content-Type', 'application/vnd.api+json');
        }
    }
}
