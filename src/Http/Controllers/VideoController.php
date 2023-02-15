<?php

namespace TomatoPHP\TomatoChat\Http\Controllers;

use Illuminate\Http\RedirectResponse;
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

class VideoController extends Controller
{
    protected Pusher|null $pusher = null;
    public function __construct()
    {
        $this->pusher = new Pusher(
            config('tomato-chat.pusher.key'),
            config('tomato-chat.pusher.secret'),
            config('tomato-chat.pusher.app_id'),
            config('tomato-chat.pusher.options'),
        );
    }

    public function confirmAudio($id){
        $seo = new SEO();
        $seo::metaByName('pusher-auth', route(config('tomato-chat.routes.name')."pusher.auth"));


        $callUser = config('tomato-chat.users_model')::find($id);
        if($callUser){
            return view('tomato-chat::pages.models.audio', [
                'user'=> $callUser
            ]);
        }

        return back();

    }

    public function confirmVideo($id){
        $seo = new SEO();
        $seo::metaByName('pusher-auth', route(config('tomato-chat.routes.name')."pusher.auth"));

        $callUser = config('tomato-chat.users_model')::find($id);
        if($callUser){
            return view('tomato-chat::pages.models.video', [
                'user'=> $callUser
            ]);
        }

        return back();
    }

    public function acceptVideo($id){
        $seo = new SEO();
        $seo::metaByName('pusher-auth', route(config('tomato-chat.routes.name')."pusher.auth"));

        $callUser = config('tomato-chat.users_model')::find($id);
        if($callUser){
            return view('tomato-chat::pages.models.accept-video', [
                'user'=> $callUser
            ]);
        }

        return back();
    }

    public function acceptAudio($id){
        $seo = new SEO();
        $seo::metaByName('pusher-auth', route(config('tomato-chat.routes.name')."pusher.auth"));

        $callUser = config('tomato-chat.users_model')::find($id);
        if($callUser){
            return view('tomato-chat::pages.models.accept-audio', [
                'user'=> $callUser
            ]);
        }

        return back();
    }

    /**
     * @param $id
     * @param $type
     * @return RedirectResponse
     * @throws ApiErrorException
     * @throws GuzzleException
     * @throws PusherException
     */
    public function index($id, $type): RedirectResponse
    {
        $seo = new SEO();
        $seo::metaByName('pusher-auth', route(config('tomato-chat.routes.name')."pusher.auth"));


        if($type === 'audio'){
            $this->pusher->trigger("private-chatify.{$id}", "audio-call", [
                'url' => route(config('tomato-chat.routes.name').'audio.accept', auth(config('tomato-chat.guard'))->user()->id)
            ]);
        }
        else {
            $this->pusher->trigger("private-chatify.{$id}", "video-call", [
                'url' => route(config('tomato-chat.routes.name').'video.accept', auth(config('tomato-chat.guard'))->user()->id)
            ]);
        }

        return redirect()->back();
    }

    /**
     * @param $id
     * @param $type
     * @return Application|Factory|View
     * @throws PusherException
     */
    public function active($id, $type): Application|Factory|View
    {
        $seo = new SEO();
        $seo::metaByName('pusher-auth', route(config('tomato-chat.routes.name')."pusher.auth"));


        $uid = rand(999, 1999);
        if($type === 'video'){
            $token = Agora::make($id)->channel('private-chatify')->uId($uid)->token();
        }
        else if($type === 'audio'){
            $token = Agora::make($id)->channel('private-chatify')->uId($uid)->audioOnly()->token();
        }

        $getUser = config('tomato-chat.users_model')::find($id);

        return view('tomato-chat::pages.video', [
            'id' => $id,
            'appID' => config('laravel-agora.agora.app_id'),
            'channelName' => 'private-chatify.'. $id,
            'token' => $token,
            'uid' => $uid,
            'type'=> $type,
            'user' => $getUser
        ]);
    }

    /**
     * @param $id
     * @param $type
     * @return Application|Factory|View
     * @throws PusherException
     */
    public function join($id, $type): Application|Factory|View
    {
        $seo = new SEO();
        $seo::metaByName('pusher-auth', route(config('tomato-chat.routes.name')."pusher.auth"));


        $uid = rand(999, 1999);
        if($type === 'video'){
            $token = Agora::make($id)->channel('private-chatify')->uId($uid)->token();
        }
        else if($type === 'audio'){
            $token = Agora::make($id)->channel('private-chatify')->uId($uid)->audioOnly()->token();
        }

        if($type === 'audio'){
            $this->pusher->trigger("private-chatify.{$id}", "client-audio-accept", [
                'url' => route(config('tomato-chat.routes.name').'chat.active', [$id, 'audio'])
            ]);
        }
        else {
            $this->pusher->trigger("private-chatify.{$id}", "client-video-accept", [
                'url' => route(config('tomato-chat.routes.name').'chat.active', [$id, 'video'])
            ]);
        }

        $getUser = config('tomato-chat.users_model')::find($id);

        return view('tomato-chat::pages.join', [
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
