<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMessageAttachmentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('message_attachments', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('messages_id')->unsigned();
            $table->foreign('messages_id')->references('id')->on('messages')->onDelete('cascade');
            $table->string('name')->index();
            $table->string('mime_type')->nullable();
            $table->boolean('is_published')->default(true);
            $table->bigInteger('size')->default(0);
            $table->string('disk');
            $table->string('path');
            $table->string('type')->nullable();
            $table->json('additional')->nullable();
            $table->string('created_by')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('message_attachments');
    }
}
