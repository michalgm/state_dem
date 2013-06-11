mysqldump -u oilchange -p state_dem legislators legislator_terms contributions_dem catcodes companies_state_details > frontend_tables.sql 
mysqldump -u oilchange -p oilchange companies >> frontend_tables.sql
gzip frontend_tables.sql
