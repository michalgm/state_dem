<?php
include "config.php";

print "<h2>Dirtiest Politicians</h2>";
print tableize(dbLookupArray("select recipient_ext_id as id, concat('<img width=\'30px\' src=\"www/can_images/', image, '\"/>') as image, full_name, recipient_state, chamber, group_concat(distinct cycle) as years, concat('$',format(sum(amount),0)) as amount from contributions_dem join legislators on recipient_ext_id = nimsp_candidate_id group by recipient_ext_id order by sum(amount) desc limit 20"));

print "<h2>Dirtiest Companies</h2>";
print tableize(dbLookupArray("select id, concat('<img width=\'30px\' src=\"www/com_images/', image_name, '\"/>') as image, name, group_concat(distinct recipient_state) as states, group_concat(distinct cycle) as years, concat('$',format(sum(amount),0)) as amount from contributions_dem join companies on company_id = id group by id order by sum(amount) desc limit 100"));





?>
