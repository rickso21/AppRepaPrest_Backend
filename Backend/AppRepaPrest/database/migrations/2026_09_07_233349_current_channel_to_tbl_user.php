// database/migrations/xxxx_xx_xx_add_current_channel_to_tbl_user.php

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCurrentChannelToTblUser extends Migration
{
    public function up()
    {
        Schema::table('tbl_user', function (Blueprint $table) {
            $table->string('current_channel')->default('general')->after('llamada_de');
        });
    }

    public function down()
    {
        Schema::table('tbl_user', function (Blueprint $table) {
            $table->dropColumn('current_channel');
        });
    }
}
