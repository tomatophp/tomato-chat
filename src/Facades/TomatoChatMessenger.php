<?php

namespace TomatoPHP\TomatoChat\Facades;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use TomatoPHP\TomatoChat\Models\ChMessage as Message;

/**
 * @property $pusher
 * @method int getMaxUploadSize()
 * @method array getAllowedImages()
 * @method array getAllowedFiles()
 * @method array getMessengerColors()
 * @method object push($channel, $event, $data)
 * @method JsonResponse|string pusherAuth($requestUser, $authUser, $channelName, $socket_id)
 * @method array fetchMessage($id, $index = null)
 * @method string messageCard($data, $viewType = null)
 * @method mixed fetchMessagesQuery($user_id)
 * @method void newMessage(array $data)
 * @method bool makeSeen(int $user_id)
 * @method Message|Collection|Builder|null getLastMessageQuery($user_id)
 * @method Collection countUnseenMessages($user_id)
 * @method string getContactItem(Collection $user)
 * @method Collection getUserWithAvatar(Collection $user)
 * @method bool inFavorite(int $user_id)
 * @method bool makeInFavorite(int $user_id,int $action)
 * @method array getSharedPhotos(int $user_id)
 * @method bool deleteConversation(int $user_id)
 * @method bool deleteMessage(int $id)
 * @method \Illuminate\Contracts\Filesystem\Filesystem storage()
 * @method string getUserAvatarUrl(string $user_avatar_name)
 * @method string getAttachmentUrl(string $attachment_name)
 *
 * @see \TomatoPHP\TomatoChat\Services\TomatoChatMessenger
 */
class TomatoChatMessenger extends Facade
{

    /**
     * @return string
     */
    protected static function getFacadeAccessor()
    {
       return 'TomatoChatMessenger';
    }
}
