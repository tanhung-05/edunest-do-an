<?php

/*
| Tiến trình con cho test đồng thời (07 §5). Gọi: php runner.php <kịch bản> <json tham số>.
| Bootstrap Laravel với DB test, chạy đúng Action thật, in một dòng JSON kết quả.
*/

use App\Actions\M01\CreateAuthenticatedSession;
use App\Actions\M01\LockAccount;
use App\Actions\M02\CreateTaxonomy;
use App\Actions\M02\SoftDeleteTaxonomy;
use App\Enums\M02\TaxonomyKind;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! str_starts_with((string) DB::selectOne('SELECT DATABASE() d')->d, 'edunest_test')) {
    fwrite(STDERR, "Sai CSDL test\n");
    exit(2);
}

[$scenario, $json] = [$argv[1], $argv[2] ?? '{}'];
$args = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

try {
    $result = match ($scenario) {
        'lock-account' => (function () use ($args) {
            $u = app(LockAccount::class)->handle(User::findOrFail($args['actor']), User::findOrFail($args['target']));

            return ['ok' => true, 'status' => $u->status->value];
        })(),
        'create-session' => (function () use ($args) {
            $session = app('session.store');
            $session->start();
            if (array_key_exists('pending', $args)) {
                $session->put(CreateAuthenticatedSession::PENDING_KEY, $args['pending']);
            }
            app(CreateAuthenticatedSession::class)->handle(new Login('web', User::findOrFail($args['user']), false));

            return ['ok' => true, 'gen' => $session->get(CreateAuthenticatedSession::SESSION_KEY)];
        })(),
        'create-category' => (function () use ($args) {
            $c = app(CreateTaxonomy::class)->handle(TaxonomyKind::Category, $args['name'], $args['parent']);

            return ['ok' => true, 'id' => $c->getKey()];
        })(),
        'soft-delete-category' => (function () use ($args) {
            app(SoftDeleteTaxonomy::class)->handle(TaxonomyKind::Category, $args['id']);

            return ['ok' => true];
        })(),
        default => throw new InvalidArgumentException("Kịch bản lạ: {$scenario}"),
    };
} catch (Throwable $e) {
    $result = ['ok' => false, 'error' => $e->getMessage(), 'class' => $e::class];
}

echo json_encode($result, JSON_UNESCAPED_UNICODE).PHP_EOL;
