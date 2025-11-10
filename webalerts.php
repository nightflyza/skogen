<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <title>Mørk Skogen</title>
    <style>
        :root {
            color-scheme: dark;
            --bg: #1e1e1e;
            --card: #252526;
            --text: #d4d4d4;
            --muted: #808080;
            --border: rgba(255, 255, 255, 0.08);
            --shadow: 0 18px 35px rgba(0, 0, 0, 0.35);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 40px clamp(20px, 5vw, 80px);
            background: var(--bg);
            color: var(--text);
        }

        header {
            max-width: 1100px;
            margin: 0 auto 40px;
            text-align: center;
        }

        header h1 {
            font-size: clamp(2rem, 4vw, 3rem);
            margin: 0 0 10px;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .states {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
            gap: clamp(16px, 3vw, 32px);
            width: min(100%, 960px);
            margin: 0 auto;
        }

        .state {
            position: relative;
            padding: 24px;
            border-radius: 20px;
            background: var(--card);
            border: 1px solid var(--border);
            box-shadow: none;
            backdrop-filter: none;
        }

        .state h2 {
            margin: 0 0 12px;
            font-size: 1.4rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .state h2 span.status {
            font-size: 1.6rem;
            line-height: 1;
        }

        .state .changed {
            margin: 0 0 16px;
            font-size: 0.95rem;
            color: var(--text);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .meta {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 18px;
            font-size: 0.9rem;
            color: var(--muted);
        }

        .meta time {
            color: var(--text);
            font-weight: 500;
        }

        .alerts {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin: 0;
            padding: 0;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            border-radius: 12px;
            background: rgba(148, 163, 184, 0.08);
            border: 1px solid rgba(148, 163, 184, 0.18);
            font-size: 0.95rem;
            color: var(--text);
            backdrop-filter: none;
        }

        .badge-icon {
            font-size: 1.3rem;
            line-height: 1;
        }

        .badge small {
            display: block;
            color: var(--muted);
            font-size: 0.8rem;
        }

        .state.empty {
            opacity: 0.6;
        }

        @media (min-width: 1200px) {
            .states {
                width: min(100%, 1100px);
            }
        }

        @media (max-width: 768px) {
            body {
                padding: 30px 16px;
            }

            header {
                text-align: left;
            }

            .states {
                width: 100%;
                grid-template-columns: 1fr;
            }

            .state {
                padding: 20px;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <header>
        <h1>Стан тривожності</h1>
    </header>

    <?php
    function renderBadges(array $items): string
    {
        $badges = array();

        foreach ($items as $item) {
            if (!is_array($item) or !isset($item['name'])) {
                continue;
            }

            $name = htmlspecialchars($item['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $changed = isset($item['changed'])
                ? htmlspecialchars($item['changed'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                : '1970-01-01 03:00:00';
            $icon = !empty($item['alert']) ? '🔴' : '🟢';

            $badges[] = '<div class="badge"><span class="badge-icon">' . $icon . '</span>' . $name . '<small>' . $changed . '</small></div>';
        }

        if (empty($badges)) {
            return '';
        }

        return '<div class="alerts">' . implode('', $badges) . '</div>';
    }

    function renderState(array $state): string
    {
        if (!isset($state['name'])) {
            return '';
        }

        $name = htmlspecialchars($state['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $changed = isset($state['changed'])
            ? htmlspecialchars($state['changed'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            : '1970-01-01 03:00:00';
        $alert = !empty($state['alert']);
        $emoji = $alert ? '🔴' : '🟢';

        $markup = '<div class="state">';
        $markup .= '<h2>' . $emoji . ' ' . $name . '</h2>';
        $markup .= '<div class="changed"><span>📅</span><span>' . $changed . '</span></div>';

        if (isset($state['districts']) and is_array($state['districts'])) {
            $districtMarkup = renderBadges($state['districts']);
            if ($districtMarkup !== '') {
                $markup .= $districtMarkup;
            }
        }

        if (isset($state['community']) and is_array($state['community'])) {
            $communityMarkup = renderBadges($state['community']);
            if ($communityMarkup !== '') {
                $markup .= $communityMarkup;
            }
        }

        $markup .= '</div>';

        return $markup;
    }

    $dataSource = __DIR__ . '/data/morkstates.json';
    if (file_exists($dataSource))  {
        $statesData = file_get_contents($dataSource);
        $morkStates = json_decode($statesData, true);
        if (!empty($morkStates) and is_array($morkStates)) {
            $statesMarkup = array();
            foreach ($morkStates as $state) {
                if (!is_array($state)) {
                    continue;
                }

                $stateMarkup = renderState($state);
                if ($stateMarkup === '') {
                    continue;
                }

                $statesMarkup[] = $stateMarkup;
            }

            if (!empty($statesMarkup)) {
                print('<div class="states">' . implode('', $statesMarkup) . '</div>');
            } else {
                print('<p>States data source is empty or invalid.</p>');
            }
        } else {
            print('<p>States data source is empty or invalid.</p>');
        }
    } else {
        print('<p>No data source found.</p>');
    }
    ?>
</body>
</html>