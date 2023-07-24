<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('conversation_id')->unsigned();
            $table->foreign('conversation_id')->references('id')->on('conversations');
            $table->bigInteger('sender_id')->unsigned();
            $table->foreign('sender_id')->references('id')->on('users');
            $table->longText('text')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('messages');
    }
};
