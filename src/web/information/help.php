<?php
session_start();
require_once '../db_config.php';

if (!isset($_SESSION['player_id'])) {
    header('Location: ../login.html');
    exit();
}

$playerId = $_SESSION['player_id'];

// Get section from URL
$section = $_GET['section'] ?? 'getting_started';

// Help content
$helpContent = [
    'getting_started' => [
        'title' => 'Getting Started',
        'icon' => '🚀',
        'content' => '
            <h3>Welcome to MagellanWars!</h3>
            <p>MagellanWars is a massively multiplayer online space strategy game where you build your galactic empire, forge alliances, and conquer the universe.</p>
            
            <h4>First Steps</h4>
            <ol>
                <li><strong>Create Your Character:</strong> Choose your race and name your empire</li>
                <li><strong>Explore Your Home Planet:</strong> Your starting planet is your foundation</li>
                <li><strong>Build Infrastructure:</strong> Construct factories, military bases, and research labs</li>
                <li><strong>Research Technology:</strong> Advance through the tech tree to unlock new capabilities</li>
                <li><strong>Expand Your Empire:</strong> Colonize new planets and grow your territory</li>
            </ol>
            
            <h4>Basic Resources</h4>
            <ul>
                <li><strong>Production Points (PP):</strong> The main currency for building and trading</li>
                <li><strong>Research Points:</strong> Used to advance technology</li>
                <li><strong>Ship Production:</strong> Determines how fast you can build fleets</li>
                <li><strong>Population:</strong> Your workforce and tax base</li>
            </ul>
            
            <h4>Your First Turn</h4>
            <p>Each turn represents one hour of real time. During each turn you can:</p>
            <ul>
                <li>Queue building construction</li>
                <li>Assign research priorities</li>
                <li>Build and deploy fleets</li>
                <li>Engage in diplomacy</li>
                <li>Trade on the black market</li>
            </ul>
        '
    ],
    'empire_management' => [
        'title' => 'Empire Management',
        'icon' => '🏛️',
        'content' => '
            <h3>Managing Your Empire</h3>
            <p>Effective empire management is key to success in MagellanWars.</p>
            
            <h4>Planet Management</h4>
            <ul>
                <li><strong>Production Ratios:</strong> Balance between factories, military bases, and research labs</li>
                <li><strong>Investment:</strong> Invest PP to accelerate growth through compound interest</li>
                <li><strong>Terraforming:</strong> Improve planet conditions for higher population capacity</li>
                <li><strong>Specialization:</strong> Focus planets on specific roles (production, research, military)</li>
            </ul>
            
            <h4>Building Types</h4>
            <table style="width: 100%; margin: 20px 0;">
                <tr>
                    <th>Building</th>
                    <th>Effect</th>
                    <th>Cost Scaling</th>
                </tr>
                <tr>
                    <td>Factory</td>
                    <td>+100 PP per level</td>
                    <td>Level × 500 PP</td>
                </tr>
                <tr>
                    <td>Military Base</td>
                    <td>+50 Ship Production</td>
                    <td>Level × 750 PP</td>
                </tr>
                <tr>
                    <td>Research Lab</td>
                    <td>+25 Research Points</td>
                    <td>Level × 1000 PP</td>
                </tr>
            </table>
            
            <h4>Trade Routes</h4>
            <p>Establish commerce between your planets for bonus income:</p>
            <ul>
                <li>Connect up to 3 partner planets per world</li>
                <li>Income based on combined production</li>
                <li>Protected by fleet presence</li>
                <li>Can be disrupted by privateers</li>
            </ul>
        '
    ],
    'military_strategy' => [
        'title' => 'Military Strategy',
        'icon' => '⚔️',
        'content' => '
            <h3>Military Operations</h3>
            <p>Building and commanding fleets is essential for defense and expansion.</p>
            
            <h4>Fleet Composition</h4>
            <ul>
                <li><strong>Fighters:</strong> Fast, cheap, good for swarming</li>
                <li><strong>Frigates:</strong> Versatile patrol ships</li>
                <li><strong>Destroyers:</strong> Balanced medium warships</li>
                <li><strong>Cruisers:</strong> Heavy firepower platforms</li>
                <li><strong>Battleships:</strong> Capital ships with maximum power</li>
            </ul>
            
            <h4>Admiral Management</h4>
            <p>Admirals provide crucial bonuses to fleet effectiveness:</p>
            <ul>
                <li>Gain experience through battles</li>
                <li>Level up to improve abilities</li>
                <li>Specialize in offense, defense, or support</li>
                <li>Can command multiple fleets as they advance</li>
            </ul>
            
            <h4>Combat Tactics</h4>
            <ol>
                <li><strong>Scouting:</strong> Always scout before attacking</li>
                <li><strong>Fleet Matching:</strong> Counter enemy ship types</li>
                <li><strong>Defensive Plans:</strong> Set up automated defense grids</li>
                <li><strong>Combined Arms:</strong> Coordinate multiple fleets</li>
                <li><strong>Supply Lines:</strong> Maintain reinforcement routes</li>
            </ol>
            
            <h4>Mission Types</h4>
            <ul>
                <li><strong>Patrol:</strong> Defend your territory</li>
                <li><strong>Siege:</strong> Attack enemy planets</li>
                <li><strong>Raid:</strong> Quick strikes for resources</li>
                <li><strong>Blockade:</strong> Cut off enemy trade</li>
                <li><strong>Invasion:</strong> Full planetary conquest</li>
            </ul>
        '
    ],
    'diplomacy_guide' => [
        'title' => 'Diplomacy Guide',
        'icon' => '🤝',
        'content' => '
            <h3>Diplomatic Relations</h3>
            <p>Diplomacy can be as powerful as military might.</p>
            
            <h4>Relationship Types</h4>
            <table style="width: 100%; margin: 20px 0;">
                <tr>
                    <th>Status</th>
                    <th>Value</th>
                    <th>Effects</th>
                </tr>
                <tr>
                    <td>Total War</td>
                    <td>-3</td>
                    <td>No restrictions, total conflict</td>
                </tr>
                <tr>
                    <td>War</td>
                    <td>-2</td>
                    <td>Open hostilities</td>
                </tr>
                <tr>
                    <td>Hostile</td>
                    <td>-1</td>
                    <td>Limited conflict allowed</td>
                </tr>
                <tr>
                    <td>Neutral</td>
                    <td>0</td>
                    <td>Default state</td>
                </tr>
                <tr>
                    <td>Peace Treaty</td>
                    <td>+1</td>
                    <td>Non-aggression pact</td>
                </tr>
                <tr>
                    <td>Trade Agreement</td>
                    <td>+2</td>
                    <td>Economic cooperation</td>
                </tr>
                <tr>
                    <td>Alliance</td>
                    <td>+3</td>
                    <td>Military cooperation</td>
                </tr>
            </table>
            
            <h4>Council Membership</h4>
            <p>Joining a council provides:</p>
            <ul>
                <li>Shared production bonuses</li>
                <li>Coordinated defense</li>
                <li>Technology sharing</li>
                <li>Diplomatic weight</li>
                <li>Protection for smaller members</li>
            </ul>
            
            <h4>Honor System</h4>
            <p>Your honor rating affects diplomatic options:</p>
            <ul>
                <li>Breaking treaties reduces honor</li>
                <li>Honoring agreements increases honor</li>
                <li>High honor unlocks special options</li>
                <li>Low honor limits diplomatic choices</li>
            </ul>
        '
    ],
    'advanced_tips' => [
        'title' => 'Advanced Tips',
        'icon' => '💡',
        'content' => '
            <h3>Advanced Strategies</h3>
            <p>Master these techniques to dominate the galaxy.</p>
            
            <h4>Economic Optimization</h4>
            <ul>
                <li><strong>Compound Growth:</strong> Reinvest early for exponential returns</li>
                <li><strong>Planet Specialization:</strong> Focus planets on single purposes</li>
                <li><strong>Trade Network:</strong> Maximize trade route efficiency</li>
                <li><strong>Black Market:</strong> Buy low, use strategically</li>
            </ul>
            
            <h4>Research Priorities</h4>
            <ol>
                <li>Information Sciences for fleet coordination</li>
                <li>Matter Sciences for weapon technology</li>
                <li>Life Sciences for population growth</li>
                <li>Social Sciences for diplomacy bonuses</li>
            </ol>
            
            <h4>Military Doctrine</h4>
            <ul>
                <li><strong>Defense in Depth:</strong> Layer your defenses</li>
                <li><strong>Force Concentration:</strong> Mass fleets for decisive battles</li>
                <li><strong>Raiding Economy:</strong> Target enemy infrastructure</li>
                <li><strong>Admiral Development:</strong> Invest in elite commanders</li>
            </ul>
            
            <h4>Diplomatic Mastery</h4>
            <ul>
                <li>Form temporary alliances against strong enemies</li>
                <li>Use trade agreements to fund military buildup</li>
                <li>Manipulate council politics</li>
                <li>Information warfare through messages</li>
            </ul>
            
            <h4>End Game Strategies</h4>
            <ul>
                <li>Control key chokepoints in the galaxy</li>
                <li>Build special projects for decisive advantages</li>
                <li>Form mega-alliances to dominate</li>
                <li>Economic victory through trade monopolies</li>
            </ul>
        '
    ],
    'shortcuts' => [
        'title' => 'Keyboard Shortcuts',
        'icon' => '⌨️',
        'content' => '
            <h3>Keyboard Shortcuts</h3>
            <p>Speed up your gameplay with these shortcuts.</p>
            
            <h4>Navigation</h4>
            <table style="width: 100%; margin: 20px 0;">
                <tr>
                    <th>Key</th>
                    <th>Action</th>
                </tr>
                <tr>
                    <td>E</td>
                    <td>Empire Management</td>
                </tr>
                <tr>
                    <td>M</td>
                    <td>Military Command</td>
                </tr>
                <tr>
                    <td>D</td>
                    <td>Diplomacy</td>
                </tr>
                <tr>
                    <td>I</td>
                    <td>Information</td>
                </tr>
                <tr>
                    <td>G</td>
                    <td>Galaxy Map</td>
                </tr>
                <tr>
                    <td>R</td>
                    <td>Rankings</td>
                </tr>
            </table>
            
            <h4>Quick Actions</h4>
            <table style="width: 100%; margin: 20px 0;">
                <tr>
                    <th>Key</th>
                    <th>Action</th>
                </tr>
                <tr>
                    <td>Space</td>
                    <td>End Turn</td>
                </tr>
                <tr>
                    <td>Tab</td>
                    <td>Next Planet</td>
                </tr>
                <tr>
                    <td>Shift+Tab</td>
                    <td>Previous Planet</td>
                </tr>
                <tr>
                    <td>Enter</td>
                    <td>Confirm Action</td>
                </tr>
                <tr>
                    <td>Esc</td>
                    <td>Cancel/Back</td>
                </tr>
            </table>
            
            <h4>Fleet Commands</h4>
            <table style="width: 100%; margin: 20px 0;">
                <tr>
                    <th>Key</th>
                    <th>Action</th>
                </tr>
                <tr>
                    <td>1-9</td>
                    <td>Select Fleet Group</td>
                </tr>
                <tr>
                    <td>Ctrl+1-9</td>
                    <td>Assign Fleet Group</td>
                </tr>
                <tr>
                    <td>A</td>
                    <td>Attack Move</td>
                </tr>
                <tr>
                    <td>S</td>
                    <td>Stop</td>
                </tr>
                <tr>
                    <td>H</td>
                    <td>Hold Position</td>
                </tr>
            </table>
        '
    ],
    'faq' => [
        'title' => 'FAQ',
        'icon' => '❓',
        'content' => '
            <h3>Frequently Asked Questions</h3>
            
            <h4>Q: How often do turns process?</h4>
            <p>A: Turns process every hour automatically. You can queue actions anytime.</p>
            
            <h4>Q: Can I change my race after starting?</h4>
            <p>A: No, race is permanent. Each race has unique advantages, so choose wisely.</p>
            
            <h4>Q: How do I increase my production?</h4>
            <p>A: Build factories, establish trade routes, invest PP, and research production technologies.</p>
            
            <h4>Q: What happens if I lose all my planets?</h4>
            <p>A: You enter protection mode and receive a new home planet to rebuild.</p>
            
            <h4>Q: How do councils work?</h4>
            <p>A: Councils are player alliances. Members share bonuses and can coordinate strategies.</p>
            
            <h4>Q: Can I attack alliance members?</h4>
            <p>A: No, alliance members cannot attack each other. Break the alliance first if needed.</p>
            
            <h4>Q: What are special projects?</h4>
            <p>A: End-game mega-structures like Death Stars and Dyson Spheres that provide huge advantages.</p>
            
            <h4>Q: How do I hire admirals?</h4>
            <p>A: Admirals appear periodically and can be hired with PP, or bought from the black market.</p>
            
            <h4>Q: Is there a victory condition?</h4>
            <p>A: Multiple victory paths exist: control 51% of the galaxy, economic dominance, or council supremacy.</p>
            
            <h4>Q: Can I rename my planets?</h4>
            <p>A: Yes, click on any planet you own in Planet Management to rename it.</p>
        '
    ]
];

$currentSection = $helpContent[$section] ?? $helpContent['getting_started'];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Help Center - MagellanWars</title>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            background: linear-gradient(to bottom, #001a33, #003366);
            color: #fff;
            font-family: 'Arial', sans-serif;
            min-height: 100vh;
        }
        
        .header {
            background: rgba(0, 0, 0, 0.9);
            padding: 15px 30px;
            border-bottom: 2px solid #00ff00;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .container {
            max-width: 1400px;
            margin: 20px auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 30px;
        }
        
        h1 {
            font-size: 36px;
            margin-bottom: 20px;
            background: linear-gradient(45deg, #00ff00, #00ffff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            grid-column: span 2;
        }
        
        .sidebar {
            background: rgba(0, 0, 0, 0.8);
            border: 1px solid #00ff00;
            border-radius: 10px;
            padding: 20px;
            height: fit-content;
            position: sticky;
            top: 20px;
        }
        
        .help-menu {
            list-style: none;
        }
        
        .help-item {
            margin-bottom: 10px;
        }
        
        .help-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px;
            background: rgba(0, 100, 0, 0.2);
            border: 1px solid #006600;
            border-radius: 8px;
            color: #fff;
            text-decoration: none;
            transition: all 0.3s;
            font-size: 14px;
        }
        
        .help-link:hover {
            background: rgba(0, 200, 0, 0.3);
            border-color: #00ff00;
            transform: translateX(5px);
        }
        
        .help-link.active {
            background: linear-gradient(45deg, rgba(0, 255, 0, 0.3), rgba(0, 255, 255, 0.2));
            border-color: #00ffff;
        }
        
        .content {
            background: rgba(0, 0, 0, 0.8);
            border: 1px solid #00ff00;
            border-radius: 10px;
            padding: 30px;
        }
        
        .content-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #00ff00;
        }
        
        .content-title {
            font-size: 32px;
            color: #00ff00;
        }
        
        .content-icon {
            font-size: 40px;
        }
        
        .help-content {
            line-height: 1.8;
            color: #ddd;
        }
        
        .help-content h3 {
            color: #00ffff;
            margin: 30px 0 15px 0;
            font-size: 24px;
        }
        
        .help-content h4 {
            color: #00ff00;
            margin: 20px 0 10px 0;
            font-size: 18px;
        }
        
        .help-content p {
            margin-bottom: 15px;
        }
        
        .help-content ul, .help-content ol {
            margin-left: 30px;
            margin-bottom: 15px;
        }
        
        .help-content li {
            margin-bottom: 8px;
        }
        
        .help-content strong {
            color: #ffff00;
        }
        
        .help-content table {
            width: 100%;
            margin: 20px 0;
            border-collapse: collapse;
            background: rgba(0, 0, 0, 0.5);
        }
        
        .help-content th {
            background: rgba(0, 255, 0, 0.2);
            padding: 10px;
            text-align: left;
            color: #00ff00;
            border: 1px solid #00ff00;
        }
        
        .help-content td {
            padding: 10px;
            border: 1px solid #006600;
        }
        
        .help-content tr:hover {
            background: rgba(0, 255, 0, 0.1);
        }
        
        .search-box {
            width: 100%;
            padding: 10px;
            background: rgba(0, 0, 0, 0.7);
            border: 1px solid #00ff00;
            color: #fff;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .search-box::placeholder {
            color: #888;
        }
        
        .quick-links {
            background: rgba(0, 100, 0, 0.1);
            border: 1px solid #00ff00;
            border-radius: 8px;
            padding: 15px;
            margin-top: 30px;
        }
        
        .quick-links h4 {
            color: #00ff00;
            margin-bottom: 10px;
        }
        
        .quick-links a {
            color: #00ffff;
            text-decoration: none;
            margin-right: 15px;
        }
        
        .quick-links a:hover {
            text-decoration: underline;
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
        <div>Help Center</div>
        <a href="../game_main.php" style="color: #00ff00; text-decoration: none;">← Back to Command Center</a>
    </div>
    
    <div class="container">
        <h1>💡 Help Center</h1>
        
        <div class="sidebar">
            <input type="text" class="search-box" placeholder="Search help topics..." id="searchBox" onkeyup="searchHelp()">
            
            <ul class="help-menu">
                <?php foreach ($helpContent as $key => $help): ?>
                <li class="help-item">
                    <a href="?section=<?php echo $key; ?>" 
                       class="help-link <?php echo ($section == $key) ? 'active' : ''; ?>">
                        <span><?php echo $help['icon']; ?></span>
                        <span><?php echo $help['title']; ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
            
            <div class="quick-links">
                <h4>Quick Links</h4>
                <a href="../empire/planet_management.php">Planet Management</a>
                <a href="../military/fleet_command.php">Fleet Command</a>
                <a href="rankings.php">Rankings</a>
                <a href="galaxy_map.php">Galaxy Map</a>
            </div>
        </div>
        
        <div class="content">
            <div class="content-header">
                <span class="content-icon"><?php echo $currentSection['icon']; ?></span>
                <h2 class="content-title"><?php echo $currentSection['title']; ?></h2>
            </div>
            
            <div class="help-content" id="helpContent">
                <?php echo $currentSection['content']; ?>
            </div>
        </div>
    </div>
    
    <script>
        function searchHelp() {
            const searchTerm = document.getElementById('searchBox').value.toLowerCase();
            const content = document.getElementById('helpContent');
            
            if (searchTerm.length > 2) {
                // Simple highlight function
                const text = content.innerHTML;
                const regex = new RegExp(`(${searchTerm})`, 'gi');
                content.innerHTML = text.replace(regex, '<mark style="background: #ffff00; color: #000;">$1</mark>');
            } else {
                // Remove highlights
                content.innerHTML = content.innerHTML.replace(/<mark[^>]*>(.*?)<\/mark>/gi, '$1');
            }
        }
    </script>
</body>
</html>