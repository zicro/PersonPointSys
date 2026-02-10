<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$errors = [];
$successMessage = null;
$personDirectory = fetchPersonDirectory($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_solde') {
        $giverName = trim((string) ($_POST['giver_person_name'] ?? ''));
        $receiverName = trim((string) ($_POST['receiver_person_name'] ?? ''));
        $soldeInput = trim((string) ($_POST['solde_delta'] ?? ''));
        $note = trim((string) ($_POST['note'] ?? ''));

        if ($giverName === '' || !in_array($giverName, $personDirectory, true)) {
            $errors[] = 'Please select a valid score giver from the list.';
        }

        if ($receiverName === '' || !in_array($receiverName, $personDirectory, true)) {
            $errors[] = 'Please select a valid person receiving the solde.';
        }

        if ($giverName !== '' && $receiverName !== '' && $giverName === $receiverName) {
            $errors[] = 'A person cannot give solde to themselves.';
        }

        if ($soldeInput === '' || filter_var($soldeInput, FILTER_VALIDATE_INT) === false) {
            $errors[] = 'Solde value must be an integer (positive or negative).';
        } else {
            $soldeDelta = (int) $soldeInput;
            if ($soldeDelta === 0) {
                $errors[] = 'Solde cannot be zero. Use a positive or negative value.';
            }
            if ($soldeDelta < -9999 || $soldeDelta > 9999) {
                $errors[] = 'Solde must be between -9999 and 9999.';
            }
        }

        if (!$errors) {
            $stmt = $pdo->prepare(
                'INSERT INTO solde_adjustments (giver_person_name, receiver_person_name, solde_delta, note)
                 VALUES (:giver_person_name, :receiver_person_name, :solde_delta, :note)'
            );
            $stmt->execute([
                ':giver_person_name' => $giverName,
                ':receiver_person_name' => $receiverName,
                ':solde_delta' => $soldeDelta,
                ':note' => $note !== '' ? $note : null,
            ]);
            $successMessage = 'Solde adjustment saved successfully.';
            $personDirectory = fetchPersonDirectory($pdo);
        }
    }
}

$personTotals = $pdo->query(
    'SELECT person_name, SUM(points_delta) AS total_points
     FROM (
        SELECT person_name, points AS points_delta FROM point_entries
        UNION ALL
        SELECT receiver_person_name AS person_name, solde_delta AS points_delta FROM solde_adjustments
     )
     GROUP BY person_name
     ORDER BY total_points DESC, person_name ASC'
)->fetchAll();

$soldeHistory = $pdo->query(
    'SELECT id, giver_person_name, receiver_person_name, solde_delta, note, created_at
     FROM solde_adjustments
     ORDER BY id DESC'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Solde - PersonPointSys</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen text-slate-800">
<main class="max-w-7xl mx-auto px-4 py-10 lg:px-8">
    <header class="mb-8 flex items-end justify-between flex-wrap gap-4">
        <div>
            <p class="text-sm uppercase tracking-widest text-indigo-600 font-semibold">PersonPointSys Admin</p>
            <h1 class="text-3xl font-bold mt-2">Manage Person Solde Adjustments</h1>
            <p class="text-slate-600 mt-2">Add positive or negative soldes that impact each person's general total points.</p>
        </div>
        <a href="index.php" class="rounded-xl bg-white border border-slate-300 px-4 py-2.5 font-medium hover:bg-slate-50 transition">Back to Dashboard</a>
    </header>

    <?php if ($successMessage): ?>
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-700"><?= e($successMessage) ?></div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-red-700">
            <ul class="list-disc pl-5 space-y-1"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <section class="grid xl:grid-cols-2 gap-6 mb-8">
        <article class="rounded-2xl bg-white p-6 shadow-sm border border-slate-200">
            <h2 class="text-xl font-semibold mb-4">Add Solde Entry</h2>

            <?php if (!$personDirectory): ?>
                <p class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-700">No persons found yet. Create at least one point entry first from the dashboard.</p>
            <?php else: ?>
                <form method="post" class="space-y-4" id="solde-form">
                    <input type="hidden" name="action" value="add_solde">
                    <div class="grid md:grid-cols-2 gap-4">
                        <label class="block">
                            <span class="text-sm text-slate-600">Score giver</span>
                            <select name="giver_person_name" id="giver-select" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:outline-none" required>
                                <option value="">Select giver</option>
                                <?php foreach ($personDirectory as $personName): ?>
                                    <option value="<?= e((string) $personName) ?>"><?= e((string) $personName) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="block">
                            <span class="text-sm text-slate-600">Receiver person</span>
                            <select name="receiver_person_name" id="receiver-select" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:outline-none" required>
                                <option value="">Select receiver</option>
                                <?php foreach ($personDirectory as $personName): ?>
                                    <option value="<?= e((string) $personName) ?>"><?= e((string) $personName) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                    <label class="block">
                        <span class="text-sm text-slate-600">Solde value (positive or negative)</span>
                        <input type="number" min="-9999" max="9999" step="1" name="solde_delta" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:outline-none" placeholder="e.g. -50 or 120">
                    </label>
                    <label class="block">
                        <span class="text-sm text-slate-600">Note (optional)</span>
                        <textarea name="note" rows="3" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:outline-none" placeholder="Reason of adjustment"></textarea>
                    </label>
                    <p class="text-sm text-slate-500">Rule: score giver cannot assign solde to themselves.</p>
                    <button class="w-full rounded-xl bg-slate-900 text-white font-medium py-2.5 hover:bg-slate-800 transition">Save Solde</button>
                </form>
            <?php endif; ?>
        </article>

        <article class="rounded-2xl bg-white p-6 shadow-sm border border-slate-200">
            <h2 class="text-xl font-semibold mb-4">General Total Points by Person</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                    <tr class="text-left text-slate-500 border-b border-slate-200"><th class="py-3 pr-3">Person</th><th class="py-3 pr-3">Total Points</th></tr>
                    </thead>
                    <tbody>
                    <?php if (!$personTotals): ?>
                        <tr><td colspan="2" class="py-4 text-slate-500">No person totals yet.</td></tr>
                    <?php else: foreach ($personTotals as $row): ?>
                        <tr class="border-b border-slate-100"><td class="py-3 pr-3 font-medium"><?= e((string) $row['person_name']) ?></td><td class="py-3 pr-3 font-semibold <?= (int) $row['total_points'] < 0 ? 'text-red-600' : 'text-slate-800' ?>"><?= number_format((int) $row['total_points']) ?></td></tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </article>
    </section>

    <section class="rounded-2xl bg-white p-6 shadow-sm border border-slate-200">
        <h2 class="text-xl font-semibold mb-4">Solde History</h2>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] text-sm">
                <thead><tr class="text-left text-slate-500 border-b border-slate-200"><th class="py-3 pr-3">#</th><th class="py-3 pr-3">Giver</th><th class="py-3 pr-3">Receiver</th><th class="py-3 pr-3">Solde</th><th class="py-3 pr-3">Note</th><th class="py-3 pr-3">Date</th></tr></thead>
                <tbody>
                <?php if (!$soldeHistory): ?>
                    <tr><td colspan="6" class="py-4 text-center text-slate-500">No solde adjustments yet.</td></tr>
                <?php else: foreach ($soldeHistory as $entry): ?>
                    <tr class="border-b border-slate-100"><td class="py-3 pr-3">#<?= (int) $entry['id'] ?></td><td class="py-3 pr-3"><?= e((string) $entry['giver_person_name']) ?></td><td class="py-3 pr-3"><?= e((string) $entry['receiver_person_name']) ?></td><td class="py-3 pr-3 font-semibold <?= (int) $entry['solde_delta'] < 0 ? 'text-red-600' : 'text-emerald-600' ?>"><?= number_format((int) $entry['solde_delta']) ?></td><td class="py-3 pr-3 text-slate-500"><?= e((string) ($entry['note'] ?? '')) ?></td><td class="py-3 pr-3 text-slate-500"><?= e((string) $entry['created_at']) ?></td></tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
<script>
(() => {
    const giver = document.getElementById('giver-select');
    const receiver = document.getElementById('receiver-select');
    if (!giver || !receiver) return;

    const refreshReceiverOptions = () => {
        const selectedGiver = giver.value;
        [...receiver.options].forEach((option) => {
            if (!option.value) return;
            option.disabled = option.value === selectedGiver;
        });

        if (receiver.value === selectedGiver) {
            receiver.value = '';
        }
    };

    giver.addEventListener('change', refreshReceiverOptions);
    refreshReceiverOptions();
})();
</script>
</body>
</html>
