<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAvatarToUsers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(app(config('tomato-chat.users_model'))->getTable(), function (Blueprint $table) {
            // if not exist, add the new column
            if (!Schema::hasColumn(app(config('tomato-chat.users_model'))->getTable(), 'avatar')) {
                $table->string('avatar')->default(config('tomato-chat.user_avatar.default'));
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('avatar');
        });
    }
}
