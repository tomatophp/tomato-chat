<?php
/**
 * -----------------------------------------------------------------
 * NOTE : There is two routes has a name (user & group),
 * any change in these two route's name may cause an issue
 * if not modified in all places that used in (e.g Tomato Chat class,
 * Controllers, Tomato Chat javascript file...).
 * -----------------------------------------------------------------
 */

use Illuminate\Support\Facades\Route;

Route::middleware(config('tomato-chat.routes.middleware'))
    ->prefix(config('tomato-chat.routes.prefix'))
    ->namespace(config('tomato-chat.routes.namespace'))
    ->name(config('tomato-chat.routes.name'))
    ->group(static function (){


    /*
    * This is the main app route [Tomato PHP Messenger]
    */
    Route::get('/', 'MessagesController@index')->name('index');

    /**
     *  Fetch info for specific id [user/group]
     */
    Route::post('/idInfo', 'MessagesController@idFetchData');

    /**
     * Send message route
     */
    Route::post('/sendMessage', 'MessagesController@send')->name('send.message');

    /**
     * Fetch messages
     */
    Route::post('/fetchMessages', 'MessagesController@fetch')->name('fetch.messages');

    /**
     * Download attachments route to create a downloadable links
     */
    Route::get('/download/{fileName}', 'MessagesController@download')->name(config('tomato-chat.attachments.download_route_name'));

    /**
     * Authentication for pusher private channels
     */
    Route::post('/chat/auth', 'MessagesController@pusherAuth')->name('pusher.auth');

    /**
     * Make messages as seen
     */
    Route::post('/makeSeen', 'MessagesController@seen')->name('messages.seen');

    /**
     * Get contacts
     */
    Route::get('/getContacts', 'MessagesController@getContacts')->name('contacts.get');

    /**
     * Update contact item data
     */
    Route::post('/updateContacts', 'MessagesController@updateContactItem')->name('contacts.update');


    /**
     * Star in favorite list
     */
    Route::post('/star', 'MessagesController@favorite')->name('star');

    /**
     * get favorites list
     */
    Route::post('/favorites', 'MessagesController@getFavorites')->name('favorites');

    /**
     * Search in messenger
     */
    Route::get('/search', 'MessagesController@search')->name('search');

    /**
     * Get shared photos
     */
    Route::post('/shared', 'MessagesController@sharedPhotos')->name('shared');

    /**
     * Delete Conversation
     */
    Route::post('/deleteConversation', 'MessagesController@deleteConversation')->name('conversation.delete');

    /**
     * Delete Message
     */
    Route::post('/deleteMessage', 'MessagesController@deleteMessage')->name('message.delete');

    /**
     * Update setting
     */
    Route::post('/updateSettings', 'MessagesController@updateSettings')->name('avatar.update');

    /**
     * Set active status
     */
    Route::post('/setActiveStatus', 'MessagesController@setActiveStatus')->name('activeStatus.set');

    /*
    * [Group] view by id
    */
    Route::get('/group/{id}', 'MessagesController@index')->name('group');

    /*
    * user view by id.
    * Note : If you added routes after the [User] which is the below one,
    * it will considered as user id.
    *
    * e.g. - The commented routes below :
    */
// Route::get('/route', function(){ return 'Munaf'; }); // works as a route
    Route::get('/{id}', 'MessagesController@index')->name('user');
// Route::get('/route', function(){ return 'Munaf'; }); // works as a user id

    if(config('tomato-chat.video_chat')){
        Route::get('video/confirm/{id}', 'VideoController@confirmVideo')->name('video.confirm');
        Route::get('video/accept/{id}', 'VideoController@acceptVideo')->name('video.accept');
    }
    if(config('tomato-chat.audio_chat')){
        Route::get('audio/confirm/{id}', 'VideoController@confirmAudio')->name('audio.confirm');
        Route::get('audio/accept/{id}', 'VideoController@acceptAudio')->name('audio.accept');
    }

    if(config('tomato-chat.video_chat') || config('tomato-chat.audio_chat')){
        Route::get('video/{id}/{type}', 'VideoController@index')->name('chat.index');
        Route::get('video/{id}/{type}/active', 'VideoController@active')->name('chat.active');
        Route::get('video/{id}/{type}/join', 'VideoController@join')->name('chat.join');
    }
});
