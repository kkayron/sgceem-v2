import re

with open('sgceem_v2_clean.sql', 'r') as f:
    sql = f.read()

# Pattern to remove triggers which start with /*!50003 CREATE*/ and end with END */ or DELIMITER ;
# In the dump they look like:
# /*!50003 CREATE*/ /*!50017 DEFINER... TRIGGER ...
# BEGIN
# ...
# END */;;
# We can use a regex to strip them.
pattern = re.compile(r'/\*!50003 CREATE\*/.*?(?:END \*/;;|END \*/;)', re.DOTALL)
sql_no_triggers = re.sub(pattern, '', sql)

# Also remove DELIMITER ;; and DELIMITER ; lines if they surround triggers
sql_no_triggers = sql_no_triggers.replace('DELIMITER ;;', '').replace('DELIMITER ;', '')

with open('sgceem_v2_notriggers.sql', 'w') as f:
    f.write(sql_no_triggers)

print("Triggers removed.")
