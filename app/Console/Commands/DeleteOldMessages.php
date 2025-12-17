<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Message;
use Carbon\Carbon;

class DeleteOldMessages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'messages:delete-old';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Elimina mensajes de chat con más de 1 día de antigüedad';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $cutoff = Carbon::now()->subDay();
        $deleted = Message::where('created_at', '<', $cutoff)->delete();
        $this->info("Mensajes eliminados: {$deleted}");
    }
}
