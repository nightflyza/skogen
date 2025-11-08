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
            font-family: "Inter", "Segoe UI", system-ui, -apple-system, sans-serif;
            background: radial-gradient(120% 80% at 50% 0%, rgba(56, 189, 248, 0.12), transparent),
                        radial-gradient(80% 120% at 0% 100%, rgba(236, 72, 153, 0.12), transparent),
                        var(--bg);
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
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: clamp(16px, 3vw, 32px);
            max-width: 1200px;
            margin: 0 auto;
        }

        .state {
            position: relative;
            padding: 24px;
            border-radius: 20px;
            background: var(--card);
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            backdrop-filter: blur(12px);
            transition: transform 220ms ease, box-shadow 220ms ease;
        }

        .state:hover {
            transform: translateY(-4px);
            box-shadow: 0 14px 40px rgba(15, 23, 42, 0.35);
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
            backdrop-filter: blur(8px);
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

        @media (max-width: 600px) {
            body {
                padding: 30px 16px;
            }

            header {
                text-align: left;
            }
        }
    </style>
</head>
<body>
    <header>
        <h1>Стан тривожності</h1>
    </header>

    <?php
    $dataSource = __DIR__ . '/data/morkstates.json';
    if (file_exists($dataSource))  {
        $statesData = file_get_contents($dataSource);
        $morkStates = json_decode($statesData, true);
        if (!empty($morkStates) and is_array($morkStates)) {
            echo '<div class="states">';

            foreach ($morkStates as $state) {
                if (!is_array($state)) {
                    continue;
                }

                $name = isset($state['name']) ? htmlspecialchars($state['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : 'unknown';
                $changed = isset($state['changed']) ? htmlspecialchars($state['changed'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '1970-01-01 03:00:00';
                $alert = !empty($state['alert']);
                $emoji = $alert ? '🔴' : '🟢';

                echo '<div class="state">';
                echo '<h2>' . $emoji . ' ' . $name . '</h2>';
                echo '<div class="changed"><span>📅</span><span>' . $changed . '</span></div>';

                $districtBadges = array();
                if (isset($state['districts']) and is_array($state['districts'])) {
                    foreach ($state['districts'] as $district) {
                        $districtBadges[] = array(
                            'name' => htmlspecialchars($district['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                            'changed' => isset($district['changed']) ? htmlspecialchars($district['changed'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '1970-01-01 03:00:00',
                            'alert' => !empty($district['alert']),
                        );
                    }
                }

                if (!empty($districtBadges)) {
                    echo '<div class="alerts">';
                    foreach ($districtBadges as $district) {
                        $icon = !empty($district['alert']) ? '🔴' : '🟢';
                        echo '<div class="badge"><span class="badge-icon">' . $icon . '</span>' . $district['name'] . '<small>' . $district['changed'] . '</small></div>';
                    }
                    echo '</div>';
                }

                $communityBadges = array();
                if (isset($state['community']) and is_array($state['community'])) {
                    foreach ($state['community'] as $community) {
                        $communityBadges[] = array(
                            'name' => htmlspecialchars($community['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                            'changed' => isset($community['changed']) ? htmlspecialchars($community['changed'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '1970-01-01 03:00:00',
                            'alert' => !empty($community['alert']),
                        );
                    }
                }

                if (!empty($communityBadges)) {
                    echo '<div class="alerts">';
                    foreach ($communityBadges as $community) {
                        $icon = !empty($community['alert']) ? '🔴' : '🟢';
                        echo '<div class="badge"><span class="badge-icon">' . $icon . '</span>' . $community['name'] . '<small>' . $community['changed'] . '</small></div>';
                    }
                    echo '</div>';
                }

                echo '</div>';
            }

            echo '</div>';
        } else {
            echo '<p>States file is empty or invalid.</p>';
        }
    } else {
        echo '<p>No data source found.</p>';
    }
    ?>
</body>
</html>