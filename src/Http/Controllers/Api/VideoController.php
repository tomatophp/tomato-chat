<?php

namespace TomatoPHP\TomatoChat\Http\Controllers\Api;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Response;
use ProtoneMedia\Splade\Facades\SEO;
use TomatoPHP\LaravelAgora\Services\Agora;
use TomatoPHP\LaravelAgora\Services\Token\RtcTokenBuilder;
use App\Http\Controllers\Controller;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Pusher\ApiErrorException;
use Pusher\Pusher;
use Pusher\PusherException;

/**
 *
 */
class VideoController extends Controller
{
    /**
     * @var Pusher|null
     */
    protected Pusher|null $pusher = null;

    /**
     * @throws PusherException
     */
    public function __construct()
    {
        $this->pusher = new Pusher(
            config('tomato-chat.pusher.key'),
            config('tomato-chat.pusher.secret'),
            config('tomato-chat.pusher.app_id'),
            config('tomato-chat.pusher.options'),
        );
    }


    /**
     * @param $id
     * @param $type
     * @return JsonResponse
     * @throws ApiErrorException
     * @throws GuzzleException
     * @throws PusherException
     */
    public function index($id, $type): JsonResponse
    {
        if($type === 'audio'){
            $this->pusher->trigger("private-chatify.{$id}", "audio-call", [
                "id" => auth(config('tomato-chat.guard'))->user()->id,
                "type" => $type,
                'url' => route(config('tomato-chat.routes.name').'audio.accept', auth(config('tomato-chat.guard'))->user()->id)
            ]);
        }
        else {
            $this->pusher->trigger("private-chatify.{$id}", "video-call", [
                "id" => auth(config('tomato-chat.guard'))->user()->id,
                "type" => $type,
                'url' => route(config('tomato-chat.routes.name').'video.accept', auth(config('tomato-chat.guard'))->user()->id)
            ]);
        }

        // send the response
        return Response::json([
            "id" => auth(config('tomato-chat.guard'))->user()->id,
            "type" => $type,
            'url' => route(config('tomato-chat.routes.name').'video.accept', auth(config('tomato-chat.guard'))->user()->id)
        ], 200);
    }


    /**
     * @param $id
     * @param $type
     * @return JsonResponse
     */
    public function active($id, $type): JsonResponse
    {
        $uid = rand(999, 1999);
        if($type === 'video'){
            $token = Agora::make($id)->channel('private-chatify')->uId($uid)->token();
        }
        else if($type === 'audio'){
            $token = Agora::make($id)->channel('private-chatify')->uId($uid)->audioOnly()->token();
        }

        $getUser = config('tomato-chat.users_model')::find($id);

        return Response::json([
            'id' => $id,
            'appID' => config('laravel-agora.agora.app_id'),
            'channelName' => 'private-chatify.'. $id,
            'token' => $token,
            'uid' => $uid,
            'type'=> $type,
            'user' => $getUser
        ], 200);
    }


    /**
     * @param $id
     * @param $type
     * @return JsonResponse
     * @throws ApiErrorException
     * @throws GuzzleException
     * @throws PusherException
     */
    public function join($id, $type): JsonResponse
    {
        $uid = rand(999, 1999);
        if($type === 'video'){
            $token = Agora::make($id)->channel('private-chatify')->uId($uid)->token();
        }
        else if($type === 'audio'){
            $token = Agora::make($id)->channel('private-chatify')->uId($uid)->audioOnly()->token();
        }

        if($type === 'audio'){
            $this->pusher->trigger("private-chatify.{$id}", "client-audio-accept", [
                "id" => $id,
                "type" => $type,
                'url' => route(config('tomato-chat.routes.name').'chat.active', [$id, 'audio'])
            ]);
        }
        else {
            $this->pusher->trigger("private-chatify.{$id}", "client-video-accept", [
                "id" => $id,
                "type" => $type,
                'url' => route(config('tomato-chat.routes.name').'chat.active', [$id, 'video'])
            ]);
        }

        $getUser = config('tomato-chat.users_model')::find($id);

        return Response::json([
            'id' => $id,
            'appID' => config('laravel-agora.agora.app_id'),
            'channelName' => 'private-chatify.'. $id,
            'token' => $token,
            'uid' => $uid,
            'type'=> $type,
            'user' => $getUser
        ]);
    }
}
