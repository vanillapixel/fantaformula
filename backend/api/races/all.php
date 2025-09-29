<?php
// Fantasy Formula 1 - All Races (aggregate pricing stats)
// GET /backend/api/races/all.php?season=2025
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../middleware/auth.php';
logRequest();
if (getRequestMethod() !== 'GET') { sendMethodNotAllowed(['GET']); }
try {
    $db = getDB();
    $seasonYear = isset($_GET['season']) ? (int)$_GET['season'] : null;
    $where=[]; $params=[];
    if ($seasonYear) { $where[]='s.year = ?'; $params[]=$seasonYear; }
    $whereSql = $where ? ('WHERE '.implode(' AND ',$where)) : '';
    $sql = "SELECT r.id, r.name, r.track_name, r.country, r.race_date, r.qualifying_date, r.round_number, r.budget_override,
                   s.year AS season_year, sr.default_budget,
                   CASE WHEN r.budget_override IS NOT NULL THEN r.budget_override ELSE sr.default_budget END AS effective_budget
            FROM races r
            JOIN seasons s ON r.season_id = s.id
            JOIN season_rules sr ON s.id = sr.season_id
            $whereSql
            ORDER BY r.race_date ASC";
    $stmt = $db->prepare($sql); $stmt->execute($params); $races = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $now = new DateTime();
    foreach ($races as &$race) {
        $race['id']=(int)$race['id'];
        $race['round_number']=(int)$race['round_number'];
        $race['season_year']=(int)$race['season_year'];
        $race['budget_override']= $race['budget_override']!==null ? (float)$race['budget_override'] : null;
        $race['default_budget']=(float)$race['default_budget'];
        $race['effective_budget']=(float)$race['effective_budget'];
        $raceDate=new DateTime($race['race_date']); $qual=new DateTime($race['qualifying_date']);
        if ($now < $qual) $race['race_status']='upcoming';
        elseif ($now >= $qual && $now < $raceDate) $race['race_status']='qualifying';
        else $race['race_status']='completed';
    }
    sendSuccess(['count'=>count($races),'season'=>$seasonYear,'races'=>$races],'All races retrieved');
} catch (Exception $e) {
    error_log('All Races error: '.$e->getMessage());
    sendServerError();
}
?>
