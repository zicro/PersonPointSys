<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$errors = [];
$successMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'set_rate') {
        $rateInput = trim((string) ($_POST['mad_rate'] ?? ''));

        if ($rateInput === '' || !is_numeric($rateInput)) {
            $errors[] = 'Please provide a valid MAD rate.';
        } else {
            $rate = (float) $rateInput;
            if ($rate < 0) {
                $errors[] = 'MAD rate cannot be negative.';
            }
        }

        if (!$errors) {
            $stmt = $pdo->prepare('UPDATE settings SET mad_rate = :rate, updated_at = CURRENT_TIMESTAMP WHERE id = 1');
            $stmt->execute([':rate' => $rate]);
            $successMessage = 'MAD conversion rate updated successfully.';
        }
    }

    if ($action === 'add_points') {
        $personName = trim((string) ($_POST['person_name'] ?? ''));
        $pointsInput = trim((string) ($_POST['points'] ?? ''));
        $note = trim((string) ($_POST['note'] ?? ''));

        if ($personName === '') {
            $errors[] = 'Person name is required.';
        }

        if ($pointsInput === '' || filter_var($pointsInput, FILTER_VALIDATE_INT) === false) {
            $errors[] = 'Points must be an integer.';
        } else {
            $points = (int) $pointsInput;
            if ($points < 1 || $points > 9999) {
                $errors[] = 'Points must be between 1 and 9999.';
            }
        }

        if (!$errors) {
            $stmt = $pdo->prepare('INSERT INTO point_entries (person_name, points, note) VALUES (:person_name, :points, :note)');
            $stmt->execute([
                ':person_name' => $personName,
                ':points' => $points,
                ':note' => $note !== '' ? $note : null,
            ]);
            $successMessage = 'Point entry created successfully.';
        }
    }

    if ($action === 'convert_entry') {
        $entryId = filter_var($_POST['entry_id'] ?? '', FILTER_VALIDATE_INT);
        if ($entryId === false) {
            $errors[] = 'Invalid entry selected for conversion.';
        }

        $rate = $pdo->query('SELECT mad_rate FROM settings WHERE id = 1')->fetchColumn();
        $rateValue = $rate === null ? null : (float) $rate;

        if ($rateValue === null || $rateValue <= 0) {
            $errors[] = 'Please define a MAD rate greater than 0 before converting points.';
        }

        if (!$errors) {
            $stmt = $pdo->prepare('SELECT id, points, converted_mad FROM point_entries WHERE id = :id');
            $stmt->execute([':id' => $entryId]);
            $entry = $stmt->fetch();

            if (!$entry) {
                $errors[] = 'Entry not found.';
            } elseif ($entry['converted_mad'] !== null) {
                $errors[] = 'This entry has already been converted.';
            } else {
                $convertedMad = round(((int) $entry['points']) * $rateValue, 2);
                $updateStmt = $pdo->prepare(
                    'UPDATE point_entries SET converted_mad = :converted_mad, converted_at = CURRENT_TIMESTAMP WHERE id = :id'
                );
                $updateStmt->execute([':converted_mad' => $convertedMad, ':id' => $entryId]);
                $successMessage = 'Entry converted to MAD successfully.';
            }
        }
    }
}

$setting = $pdo->query('SELECT mad_rate FROM settings WHERE id = 1')->fetch();
$currentRate = $setting && $setting['mad_rate'] !== null ? (float) $setting['mad_rate'] : null;
$isRateEnabled = $currentRate !== null && $currentRate > 0;

$entries = $pdo->query(
    'SELECT id, person_name, points, note, converted_mad, converted_at, created_at
     FROM point_entries
     ORDER BY id DESC'
)->fetchAll();

$perPersonTotals = $pdo->query(
    'SELECT person_name, SUM(points_delta) AS total_points
     FROM (
        SELECT person_name, points AS points_delta FROM point_entries
        UNION ALL
        SELECT receiver_person_name AS person_name, solde_delta AS points_delta FROM solde_adjustments
     )
     GROUP BY person_name
     ORDER BY total_points DESC, person_name ASC'
)->fetchAll();

$totalPoints = (int) ($pdo->query(
    'SELECT COALESCE(SUM(points_delta), 0)
     FROM (
       SELECT points AS points_delta FROM point_entries
       UNION ALL
       SELECT solde_delta AS points_delta FROM solde_adjustments
     )'
)->fetchColumn() ?: 0);
$totalConvertedMad = (float) ($pdo->query('SELECT COALESCE(SUM(converted_mad), 0) FROM point_entries')->fetchColumn() ?: 0);
$totalPending = (int) ($pdo->query('SELECT COUNT(*) FROM point_entries WHERE converted_mad IS NULL')->fetchColumn() ?: 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Person Point System</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen text-slate-800">
<main class="max-w-7xl mx-auto px-4 py-10 lg:px-8">
    <header class="mb-8 flex flex-wrap gap-4 items-end justify-between">
        <div>
            <p class="text-sm uppercase tracking-widest text-indigo-600 font-semibold">PersonPointSys</p>
            <h1 class="text-3xl font-bold mt-2">Point Management & MAD Conversion</h1>
            <p class="text-slate-600 mt-2">Manage points, apply soldes from admin page, and convert to MAD when rate is active.</p>
        </div>
        <a href="admin-solde.php" class="rounded-xl bg-slate-900 text-white px-4 py-2.5 font-medium hover:bg-slate-800 transition">Open Admin Solde Page</a>
    </header>

    <?php if ($successMessage): ?>
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-700"><?= e($successMessage) ?></div>
    <?php endif; ?>
    <?php if ($errors): ?>
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-red-700"><ul class="list-disc pl-5 space-y-1"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <section class="grid md:grid-cols-3 gap-4 mb-8">
        <article class="rounded-2xl bg-white p-5 shadow-sm border border-slate-200"><p class="text-sm text-slate-500">Global Total Points</p><p class="text-2xl font-bold mt-2"><?= number_format($totalPoints) ?></p></article>
        <article class="rounded-2xl bg-white p-5 shadow-sm border border-slate-200"><p class="text-sm text-slate-500">Converted MAD</p><p class="text-2xl font-bold mt-2"><?= number_format($totalConvertedMad, 2) ?> MAD</p></article>
        <article class="rounded-2xl bg-white p-5 shadow-sm border border-slate-200"><p class="text-sm text-slate-500">Pending Conversions</p><p class="text-2xl font-bold mt-2"><?= number_format($totalPending) ?></p></article>
    </section>

    <section class="grid xl:grid-cols-2 gap-6 mb-8">
        <article class="rounded-2xl bg-white p-6 shadow-sm border border-slate-200">
            <h2 class="text-xl font-semibold mb-4">1) Define MAD Rate</h2>
            <form method="post" class="space-y-4">
                <input type="hidden" name="action" value="set_rate">
                <label class="block"><span class="text-sm text-slate-600">MAD per point</span><input type="number" step="0.01" min="0" name="mad_rate" id="mad-rate" value="<?= $currentRate !== null ? e((string) $currentRate) : '' ?>" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:outline-none" placeholder="e.g. 0.75"></label>
                <button class="w-full rounded-xl bg-indigo-600 text-white font-medium py-2.5 hover:bg-indigo-500 transition">Save Rate</button>
                <p class="text-sm text-slate-500">Current status: <span id="rate-status" class="font-semibold <?= $isRateEnabled ? 'text-emerald-600' : 'text-amber-600' ?>"><?= $isRateEnabled ? 'Ready for conversion' : 'Rate missing or zero (conversion disabled)' ?></span></p>
            </form>
        </article>

        <article class="rounded-2xl bg-white p-6 shadow-sm border border-slate-200">
            <h2 class="text-xl font-semibold mb-4">2) Create Point Entry</h2>
            <form method="post" class="space-y-4">
                <input type="hidden" name="action" value="add_points">
                <div class="grid md:grid-cols-2 gap-4">
                    <label class="block"><span class="text-sm text-slate-600">Person name</span><input type="text" name="person_name" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:outline-none" placeholder="Enter full name"></label>
                    <label class="block"><span class="text-sm text-slate-600">Points (1-9999)</span><input type="number" name="points" min="1" max="9999" step="1" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:outline-none" placeholder="250"></label>
                </div>
                <label class="block"><span class="text-sm text-slate-600">Note (optional)</span><textarea name="note" rows="3" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:outline-none" placeholder="Context for this entry"></textarea></label>
                <button class="w-full rounded-xl bg-slate-900 text-white font-medium py-2.5 hover:bg-slate-800 transition">Save Entry</button>
            </form>
        </article>
    </section>

    <section class="grid xl:grid-cols-2 gap-6 mb-8">
        <article class="rounded-2xl bg-white p-6 shadow-sm border border-slate-200">
            <h2 class="text-xl font-semibold mb-4">3) Person General Total Points (Points + Solde)</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm"><thead><tr class="text-left text-slate-500 border-b border-slate-200"><th class="py-3 pr-3">Person</th><th class="py-3 pr-3">General Total Points</th></tr></thead><tbody>
                    <?php if (!$perPersonTotals): ?><tr><td colspan="2" class="py-4 text-slate-500">No data yet.</td></tr><?php else: foreach ($perPersonTotals as $row): ?>
                    <tr class="border-b border-slate-100"><td class="py-3 pr-3 font-medium"><?= e((string) $row['person_name']) ?></td><td class="py-3 pr-3 <?= (int) $row['total_points'] < 0 ? 'text-red-600' : 'text-slate-800' ?>"><?= number_format((int) $row['total_points']) ?></td></tr>
                    <?php endforeach; endif; ?>
                </tbody></table>
            </div>
        </article>

        <article class="rounded-2xl bg-white p-6 shadow-sm border border-slate-200">
            <h2 class="text-xl font-semibold mb-4">4) Entries & Conversion</h2>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-sm">
                    <thead><tr class="text-left text-slate-500 border-b border-slate-200"><th class="py-3 pr-3">Person</th><th class="py-3 pr-3">Points</th><th class="py-3 pr-3">Converted (MAD)</th><th class="py-3 pr-3">Action</th></tr></thead>
                    <tbody>
                    <?php if (!$entries): ?><tr><td colspan="4" class="py-6 text-center text-slate-500">No entries yet.</td></tr><?php else: foreach ($entries as $entry): $isConverted = $entry['converted_mad'] !== null; ?>
                        <tr class="border-b border-slate-100">
                            <td class="py-3 pr-3"><?= e((string) $entry['person_name']) ?></td>
                            <td class="py-3 pr-3"><?= number_format((int) $entry['points']) ?></td>
                            <td class="py-3 pr-3"><?= $isConverted ? number_format((float) $entry['converted_mad'], 2) . ' MAD' : '<span class="text-amber-600">Pending</span>' ?></td>
                            <td class="py-3 pr-3"><form method="post"><input type="hidden" name="action" value="convert_entry"><input type="hidden" name="entry_id" value="<?= (int) $entry['id'] ?>"><button type="submit" class="convert-btn rounded-lg px-3 py-2 text-white text-xs font-semibold transition <?= ($isConverted || !$isRateEnabled) ? 'bg-slate-300 cursor-not-allowed' : 'bg-emerald-600 hover:bg-emerald-500' ?>" <?= ($isConverted || !$isRateEnabled) ? 'disabled' : '' ?>><?= $isConverted ? 'Converted' : 'Convert to MAD' ?></button></form></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </article>
    </section>
</main>
<script>
(() => {
    const rateInput = document.getElementById('mad-rate');
    const rateStatus = document.getElementById('rate-status');
    const convertButtons = document.querySelectorAll('.convert-btn');
    const refreshState = () => {
        const rate = parseFloat(rateInput.value);
        const active = Number.isFinite(rate) && rate > 0;
        rateStatus.textContent = active ? 'Ready for conversion' : 'Rate missing or zero (conversion disabled)';
        rateStatus.classList.toggle('text-emerald-600', active);
        rateStatus.classList.toggle('text-amber-600', !active);
        convertButtons.forEach((button) => {
            if (button.textContent.trim() === 'Converted') return;
            button.disabled = !active;
            button.classList.toggle('bg-slate-300', !active);
            button.classList.toggle('cursor-not-allowed', !active);
            button.classList.toggle('bg-emerald-600', active);
        });
    };
    rateInput.addEventListener('input', refreshState);
    refreshState();
})();
</script>
</body>
</html>
