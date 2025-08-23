<?php
session_start();
require_once '../db_config.php';

if (!isset($_SESSION['player_id'])) {
    header('Location: ../login.html');
    exit();
}

$playerId = $_SESSION['player_id'];

// Get category from URL
$category = $_GET['category'] ?? 'races';

// Encyclopedia data
$encyclopedia = [
    'races' => [
        'title' => 'Galactic Races',
        'icon' => '👽',
        'entries' => [
            [
                'name' => 'Human',
                'image' => '🧑‍🚀',
                'description' => 'Adaptable and ambitious, Humans are known for their versatility and rapid technological advancement.',
                'traits' => [
                    'Production Bonus' => '+10%',
                    'Research Speed' => 'Standard',
                    'Fleet Capacity' => 'Standard',
                    'Special Ability' => 'Adaptive Learning - Gains bonus from defeated enemies'
                ],
                'history' => 'Originating from Earth, Humans have spread across the galaxy with their trademark determination and ingenuity.'
            ],
            [
                'name' => 'Targro',
                'image' => '🦎',
                'description' => 'A reptilian warrior race focused on honor and combat prowess.',
                'traits' => [
                    'Military Strength' => '+15%',
                    'Ship Hull Points' => '+10%',
                    'Research Speed' => '-10%',
                    'Special Ability' => 'Battle Fury - Damage increases when outnumbered'
                ],
                'history' => 'The Targro emerged from a harsh desert world, developing a culture centered on strength and survival.'
            ],
            [
                'name' => 'Bukka',
                'image' => '🐙',
                'description' => 'Aquatic beings with advanced biotechnology and psychic abilities.',
                'traits' => [
                    'Research Bonus' => '+20%',
                    'Shield Technology' => '+15%',
                    'Production Speed' => '-5%',
                    'Special Ability' => 'Mind Link - Improved fleet coordination'
                ],
                'history' => 'From the ocean depths of Bukka Prime, this ancient race has mastered both biological and energy sciences.'
            ],
            [
                'name' => 'Xeloss',
                'image' => '🤖',
                'description' => 'Synthetic intelligences focused on efficiency and logic.',
                'traits' => [
                    'Production Efficiency' => '+25%',
                    'Computer Systems' => '+20%',
                    'Diplomacy' => '-15%',
                    'Special Ability' => 'Drone Networks - Automated defense systems'
                ],
                'history' => 'Created by an extinct precursor race, the Xeloss achieved sentience and now seek their place in the galaxy.'
            ],
            [
                'name' => 'Agerus',
                'image' => '🦅',
                'description' => 'Avian traders and diplomats with extensive merchant networks.',
                'traits' => [
                    'Trade Income' => '+30%',
                    'Diplomacy Bonus' => '+20%',
                    'Military Strength' => '-10%',
                    'Special Ability' => 'Trade Winds - Bonus resources from trade routes'
                ],
                'history' => 'Masters of commerce, the Agerus built their empire through trade rather than conquest.'
            ]
        ]
    ],
    'technologies' => [
        'title' => 'Technology Tree',
        'icon' => '🔬',
        'entries' => [
            [
                'name' => 'Information Sciences',
                'image' => '💻',
                'description' => 'Computer systems, AI, and data processing technologies.',
                'levels' => [
                    'Level 1' => 'Basic Computing - Unlocks simple automation',
                    'Level 5' => 'Quantum Processors - Fleet coordination +10%',
                    'Level 10' => 'AI Integration - All systems efficiency +15%',
                    'Level 15' => 'Sentient Networks - Autonomous defense grids'
                ]
            ],
            [
                'name' => 'Life Sciences',
                'image' => '🧬',
                'description' => 'Biology, terraforming, and population technologies.',
                'levels' => [
                    'Level 1' => 'Basic Medicine - Population growth +5%',
                    'Level 5' => 'Genetic Engineering - Terraform speed +20%',
                    'Level 10' => 'Bioships - Organic hull regeneration',
                    'Level 15' => 'Immortality Serum - Admiral lifespan doubled'
                ]
            ],
            [
                'name' => 'Matter Sciences',
                'image' => '⚛️',
                'description' => 'Materials, weapons, and construction technologies.',
                'levels' => [
                    'Level 1' => 'Alloy Composites - Ship armor +5%',
                    'Level 5' => 'Plasma Weapons - Damage output +15%',
                    'Level 10' => 'Antimatter Reactors - Ship speed +25%',
                    'Level 15' => 'Dark Matter Manipulation - Cloaking devices'
                ]
            ],
            [
                'name' => 'Social Sciences',
                'image' => '🏛️',
                'description' => 'Government, diplomacy, and cultural technologies.',
                'levels' => [
                    'Level 1' => 'Basic Administration - Production +5%',
                    'Level 5' => 'Advanced Diplomacy - Treaty benefits +20%',
                    'Level 10' => 'Unified Government - No rebellion',
                    'Level 15' => 'Galactic Senate - Council bonuses doubled'
                ]
            ]
        ]
    ],
    'ships' => [
        'title' => 'Ship Classes',
        'icon' => '🚀',
        'entries' => [
            [
                'name' => 'Fighter',
                'image' => '✈️',
                'description' => 'Small, fast attack craft ideal for swarming tactics.',
                'stats' => [
                    'Hull Points' => '50',
                    'Speed' => 'Very Fast',
                    'Weapons' => '1 Light',
                    'Cost' => '100 PP'
                ]
            ],
            [
                'name' => 'Frigate',
                'image' => '🛸',
                'description' => 'Versatile light warship for patrol and escort duties.',
                'stats' => [
                    'Hull Points' => '200',
                    'Speed' => 'Fast',
                    'Weapons' => '2 Medium',
                    'Cost' => '500 PP'
                ]
            ],
            [
                'name' => 'Destroyer',
                'image' => '🚢',
                'description' => 'Medium warship with balanced offense and defense.',
                'stats' => [
                    'Hull Points' => '500',
                    'Speed' => 'Moderate',
                    'Weapons' => '3 Medium, 1 Heavy',
                    'Cost' => '1500 PP'
                ]
            ],
            [
                'name' => 'Cruiser',
                'image' => '⚓',
                'description' => 'Heavy warship forming the backbone of most fleets.',
                'stats' => [
                    'Hull Points' => '1000',
                    'Speed' => 'Slow',
                    'Weapons' => '2 Heavy, 4 Medium',
                    'Cost' => '3000 PP'
                ]
            ],
            [
                'name' => 'Battleship',
                'image' => '🛡️',
                'description' => 'Massive capital ship with devastating firepower.',
                'stats' => [
                    'Hull Points' => '2500',
                    'Speed' => 'Very Slow',
                    'Weapons' => '4 Heavy, 6 Medium',
                    'Cost' => '8000 PP'
                ]
            ]
        ]
    ],
    'buildings' => [
        'title' => 'Planetary Structures',
        'icon' => '🏗️',
        'entries' => [
            [
                'name' => 'Factory',
                'image' => '🏭',
                'description' => 'Industrial complex for production output.',
                'effects' => [
                    'Production' => '+100 PP per level',
                    'Pollution' => '+1 per level',
                    'Max Level' => '20',
                    'Cost Scaling' => 'Level × 500 PP'
                ]
            ],
            [
                'name' => 'Military Base',
                'image' => '🎖️',
                'description' => 'Training facility and shipyard for military forces.',
                'effects' => [
                    'Ship Production' => '+50 per level',
                    'Defense Bonus' => '+5% per level',
                    'Max Level' => '15',
                    'Cost Scaling' => 'Level × 750 PP'
                ]
            ],
            [
                'name' => 'Research Lab',
                'image' => '🔭',
                'description' => 'Scientific facility for technological advancement.',
                'effects' => [
                    'Research Points' => '+25 per level',
                    'Tech Speed' => '+2% per level',
                    'Max Level' => '15',
                    'Cost Scaling' => 'Level × 1000 PP'
                ]
            ],
            [
                'name' => 'Terraforming Station',
                'image' => '🌍',
                'description' => 'Environmental control to improve planet conditions.',
                'effects' => [
                    'Atmosphere' => 'Gradual improvement',
                    'Temperature' => 'Optimization',
                    'Population Cap' => '+10% when complete',
                    'Time' => '50-100 turns'
                ]
            ]
        ]
    ],
    'projects' => [
        'title' => 'Special Projects',
        'icon' => '⚡',
        'entries' => [
            [
                'name' => 'Death Star',
                'image' => '💀',
                'description' => 'Ultimate weapon capable of destroying entire planets.',
                'requirements' => [
                    'Research Level' => 'Matter Sciences 20',
                    'Cost' => '1,000,000 PP',
                    'Build Time' => '200 turns',
                    'Maintenance' => '10,000 PP/turn'
                ]
            ],
            [
                'name' => 'Wormhole Generator',
                'image' => '🌀',
                'description' => 'Creates instant travel between two points in space.',
                'requirements' => [
                    'Research Level' => 'Information Sciences 18',
                    'Cost' => '500,000 PP',
                    'Build Time' => '100 turns',
                    'Range' => '10 clusters'
                ]
            ],
            [
                'name' => 'Dyson Sphere',
                'image' => '☀️',
                'description' => 'Harnesses entire star output for unlimited energy.',
                'requirements' => [
                    'Research Level' => 'Matter Sciences 15',
                    'Cost' => '2,000,000 PP',
                    'Build Time' => '500 turns',
                    'Benefit' => 'Unlimited production in system'
                ]
            ]
        ]
    ],
    'gameplay' => [
        'title' => 'Game Mechanics',
        'icon' => '🎮',
        'entries' => [
            [
                'name' => 'Turn System',
                'image' => '⏰',
                'description' => 'How time progresses in the galaxy.',
                'details' => [
                    'Turn Length' => '1 hour real time',
                    'Actions Per Turn' => 'Unlimited planning',
                    'Processing' => 'Simultaneous resolution',
                    'Tick Rate' => 'Every 10 minutes for updates'
                ]
            ],
            [
                'name' => 'Combat System',
                'image' => '⚔️',
                'description' => 'How battles are resolved.',
                'details' => [
                    'Initiative' => 'Based on fleet speed',
                    'Targeting' => 'Largest ships first',
                    'Damage' => 'Weapon power vs armor/shields',
                    'Retreat' => 'Possible after 3 rounds'
                ]
            ],
            [
                'name' => 'Diplomacy',
                'image' => '🤝',
                'description' => 'Managing relations with other players.',
                'details' => [
                    'Treaties' => 'Peace, Trade, Alliance',
                    'Council' => 'Join for shared benefits',
                    'Relations' => '-3 (War) to +3 (Alliance)',
                    'Honor' => 'Affects diplomatic options'
                ]
            ],
            [
                'name' => 'Economy',
                'image' => '💰',
                'description' => 'Resource management and growth.',
                'details' => [
                    'Production Points' => 'Main currency',
                    'Research Points' => 'Tech advancement',
                    'Trade Income' => 'From routes and treaties',
                    'Investment' => 'Compound growth system'
                ]
            ]
        ]
    ]
];

$currentCategory = $encyclopedia[$category] ?? $encyclopedia['races'];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Encyclopedia - MagellanWars</title>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            background: linear-gradient(to bottom, #1a0033, #2a0044);
            color: #fff;
            font-family: 'Arial', sans-serif;
            min-height: 100vh;
        }
        
        .header {
            background: rgba(0, 0, 0, 0.9);
            padding: 15px 30px;
            border-bottom: 2px solid #ff00ff;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .container {
            max-width: 1400px;
            margin: 20px auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 30px;
        }
        
        h1 {
            font-size: 36px;
            margin-bottom: 20px;
            background: linear-gradient(45deg, #ff00ff, #ffaa00);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            grid-column: span 2;
        }
        
        .sidebar {
            background: rgba(0, 0, 0, 0.8);
            border: 1px solid #ff00ff;
            border-radius: 10px;
            padding: 20px;
            height: fit-content;
            position: sticky;
            top: 20px;
        }
        
        .category-list {
            list-style: none;
        }
        
        .category-item {
            margin-bottom: 10px;
        }
        
        .category-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px;
            background: rgba(100, 0, 100, 0.3);
            border: 1px solid #660066;
            border-radius: 8px;
            color: #fff;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .category-link:hover {
            background: rgba(200, 0, 200, 0.4);
            border-color: #ff00ff;
            transform: translateX(5px);
        }
        
        .category-link.active {
            background: linear-gradient(45deg, rgba(255, 0, 255, 0.4), rgba(255, 170, 0, 0.3));
            border-color: #ffaa00;
        }
        
        .content {
            background: rgba(0, 0, 0, 0.8);
            border: 1px solid #ff00ff;
            border-radius: 10px;
            padding: 30px;
        }
        
        .content-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #ff00ff;
        }
        
        .content-title {
            font-size: 32px;
            color: #ffaa00;
        }
        
        .content-icon {
            font-size: 40px;
        }
        
        .entry {
            background: rgba(50, 0, 50, 0.3);
            border: 1px solid #660066;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
            transition: all 0.3s;
        }
        
        .entry:hover {
            background: rgba(100, 0, 100, 0.3);
            border-color: #ff00ff;
            box-shadow: 0 5px 20px rgba(255, 0, 255, 0.2);
        }
        
        .entry-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .entry-icon {
            font-size: 48px;
        }
        
        .entry-name {
            font-size: 24px;
            color: #ff00ff;
            font-weight: bold;
        }
        
        .entry-description {
            color: #ddd;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        
        .entry-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .detail-section {
            background: rgba(0, 0, 0, 0.5);
            padding: 15px;
            border-radius: 8px;
            border-left: 3px solid #ffaa00;
        }
        
        .detail-title {
            color: #ffaa00;
            font-weight: bold;
            margin-bottom: 10px;
            text-transform: uppercase;
            font-size: 12px;
        }
        
        .detail-list {
            list-style: none;
        }
        
        .detail-item {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px solid #333;
        }
        
        .detail-item:last-child {
            border-bottom: none;
        }
        
        .detail-key {
            color: #aaa;
            font-size: 14px;
        }
        
        .detail-value {
            color: #00ffff;
            font-weight: bold;
            font-size: 14px;
        }
        
        .entry-history {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #660066;
            color: #bbb;
            font-style: italic;
            line-height: 1.6;
        }
        
        .search-box {
            width: 100%;
            padding: 10px;
            background: rgba(0, 0, 0, 0.7);
            border: 1px solid #ff00ff;
            color: #fff;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .search-box::placeholder {
            color: #888;
        }
        
        @media (max-width: 768px) {
            .container {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                position: static;
            }
            
            h1 {
                grid-column: span 1;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div>Galactic Encyclopedia</div>
        <a href="../game_main.php" style="color: #ff00ff; text-decoration: none;">← Back to Command Center</a>
    </div>
    
    <div class="container">
        <h1>📚 Galactic Encyclopedia</h1>
        
        <div class="sidebar">
            <input type="text" class="search-box" placeholder="Search encyclopedia..." id="searchBox" onkeyup="filterEntries()">
            
            <ul class="category-list">
                <?php foreach ($encyclopedia as $key => $cat): ?>
                <li class="category-item">
                    <a href="?category=<?php echo $key; ?>" 
                       class="category-link <?php echo ($category == $key) ? 'active' : ''; ?>">
                        <span><?php echo $cat['icon']; ?></span>
                        <span><?php echo $cat['title']; ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        
        <div class="content">
            <div class="content-header">
                <span class="content-icon"><?php echo $currentCategory['icon']; ?></span>
                <h2 class="content-title"><?php echo $currentCategory['title']; ?></h2>
            </div>
            
            <div id="entriesContainer">
                <?php foreach ($currentCategory['entries'] as $entry): ?>
                <div class="entry" data-searchable="<?php echo strtolower($entry['name'] . ' ' . $entry['description']); ?>">
                    <div class="entry-header">
                        <span class="entry-icon"><?php echo $entry['image']; ?></span>
                        <span class="entry-name"><?php echo $entry['name']; ?></span>
                    </div>
                    
                    <div class="entry-description">
                        <?php echo $entry['description']; ?>
                    </div>
                    
                    <div class="entry-details">
                        <?php 
                        // Display different detail sections based on category
                        if (isset($entry['traits'])): ?>
                            <div class="detail-section">
                                <div class="detail-title">Racial Traits</div>
                                <ul class="detail-list">
                                    <?php foreach ($entry['traits'] as $key => $value): ?>
                                    <li class="detail-item">
                                        <span class="detail-key"><?php echo $key; ?></span>
                                        <span class="detail-value"><?php echo $value; ?></span>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($entry['levels'])): ?>
                            <div class="detail-section">
                                <div class="detail-title">Technology Levels</div>
                                <ul class="detail-list">
                                    <?php foreach ($entry['levels'] as $key => $value): ?>
                                    <li class="detail-item">
                                        <span class="detail-key"><?php echo $key; ?></span>
                                        <span class="detail-value"><?php echo $value; ?></span>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($entry['stats'])): ?>
                            <div class="detail-section">
                                <div class="detail-title">Statistics</div>
                                <ul class="detail-list">
                                    <?php foreach ($entry['stats'] as $key => $value): ?>
                                    <li class="detail-item">
                                        <span class="detail-key"><?php echo $key; ?></span>
                                        <span class="detail-value"><?php echo $value; ?></span>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($entry['effects'])): ?>
                            <div class="detail-section">
                                <div class="detail-title">Effects</div>
                                <ul class="detail-list">
                                    <?php foreach ($entry['effects'] as $key => $value): ?>
                                    <li class="detail-item">
                                        <span class="detail-key"><?php echo $key; ?></span>
                                        <span class="detail-value"><?php echo $value; ?></span>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($entry['requirements'])): ?>
                            <div class="detail-section">
                                <div class="detail-title">Requirements</div>
                                <ul class="detail-list">
                                    <?php foreach ($entry['requirements'] as $key => $value): ?>
                                    <li class="detail-item">
                                        <span class="detail-key"><?php echo $key; ?></span>
                                        <span class="detail-value"><?php echo $value; ?></span>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($entry['details'])): ?>
                            <div class="detail-section">
                                <div class="detail-title">Details</div>
                                <ul class="detail-list">
                                    <?php foreach ($entry['details'] as $key => $value): ?>
                                    <li class="detail-item">
                                        <span class="detail-key"><?php echo $key; ?></span>
                                        <span class="detail-value"><?php echo $value; ?></span>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (isset($entry['history'])): ?>
                    <div class="entry-history">
                        <?php echo $entry['history']; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <script>
        function filterEntries() {
            const searchTerm = document.getElementById('searchBox').value.toLowerCase();
            const entries = document.querySelectorAll('.entry');
            
            entries.forEach(entry => {
                const searchable = entry.getAttribute('data-searchable');
                if (searchable.includes(searchTerm)) {
                    entry.style.display = 'block';
                } else {
                    entry.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>