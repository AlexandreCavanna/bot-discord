<?php

include __DIR__.'/vendor/autoload.php';

use Discord\Builders\Components\ActionRow;
use Discord\Builders\Components\TextInput;
use Discord\Builders\MessageBuilder;
use Discord\Discord;
use Discord\InteractionType;
use Discord\Parts\Guild\Guild;
use Discord\Parts\Guild\ScheduledEvent;
use Discord\Parts\Interactions\Command\Command;
use Discord\Parts\Interactions\Interaction;
use Discord\WebSockets\Event;
use React\EventLoop\Loop;
use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->loadEnv(__DIR__.'/.env');

$loop = Loop::get();

$discord = new Discord([
    'token' => $_ENV['TOKEN'],
    'loop' => $loop,
]);

$discord->on('ready', function (Discord $discord) {
    $guild = $discord->guilds->find(function (Guild $guild) {
        return ($_ENV['GUILD_ID'] == $guild->id) ? $guild : null;
    });

    $command = new Command($discord, [
        'id' => 1,
        'application_id' => $_ENV['APPLICATION_ID'],
        'guild_id' => $_ENV['GUILD_ID'],
        'name' => 'csgoevent',
        'type' => Command::CHAT_INPUT,
        'description' => 'Cette commande crée un évènement CS GO.',
    ]);

    $guild->commands->save($command);

    new Interaction($discord, [
        'id' => 1,
        'type' => InteractionType::APPLICATION_COMMAND,
        'token' => $_ENV['TOKEN'],
        'application_id' => $_ENV['APPLICATION_ID'],
        'guild_id' => $_ENV['GUILD_ID'],
        'data' => [
            'id' => 1,
            'name' => 'csgoevent',
            'type' => Command::CHAT_INPUT,
            'guild_id' => $_ENV['GUILD_ID'],
        ],
        'version' => 1,
    ]);

    $discord->on(EVENT::INTERACTION_CREATE, function (Interaction $interaction) use ($guild, $discord) {
        $actionRow = ActionRow::new()
            ->addComponent(TextInput::new('Quelle heure ?', TextInput::STYLE_SHORT));

        new Interaction($discord, [
            'id' => 2,
            'type' => InteractionType::MODAL_SUBMIT,
            'token' => $_ENV['TOKEN'],
            'application_id' => $_ENV['APPLICATION_ID'],
            'guild_id' => $_ENV['GUILD_ID'],
            'data' => [
                'custom_id' => 'modalCsgo',
            ],
            'version' => 1,
        ]);

        $interaction->showModal(
            'Quand l\'évènement doit commencer ?',
            'modalCsgo', [$actionRow],
            function (Interaction $interaction) use ($guild) {
                foreach ($interaction->data->components as $actionrow) {
                    foreach ($actionrow->components as $component) {
                        $checkTimeEvent = (1 === preg_match('/^([0-1]?[0-9]|2[0-3])[hH][0-5][0-9]$/', $component->value));

                        if ($checkTimeEvent) {
                            $hour = substr($component->value, 0, 2);
                            $minute = substr($component->value, 3, 2);

                            $timestamp = new DateTime();

                            $scheduledEvent = $guild->guild_scheduled_events->create([
                                'name' => 'CS GO',
                                'description' => 'Détruire des culs.',
                                'scheduled_start_time' => date($timestamp::ISO8601, $timestamp->setTime((int) $hour, (int) $minute)->getTimestamp()),
                                'scheduled_end_time' => date($timestamp::ISO8601, strtotime('+2 hours', $timestamp->setTime((int) $hour, (int) $minute)->getTimestamp())),
                                'entity_type' => ScheduledEvent::ENTITY_TYPE_EXTERNAL,
                                'entity_metadata' => ['location' => 'Faceit'],
                                'privacy_level' => ScheduledEvent::PRIVACY_LEVEL_GUILD_ONLY,
                            ]);

                            $guild->guild_scheduled_events->save($scheduledEvent)->done();

                            $message = MessageBuilder::new()
                                ->setContent('L\'évènement CS GO a bien été créé à '.$component->value.' 💥🔫');

                            $interaction->respondWithMessage($message);
                        }
                    }
                }
            }
        );
    });
});

$discord->run();
