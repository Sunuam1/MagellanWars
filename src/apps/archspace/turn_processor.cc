/**
 * Simple turn processor helper
 * This ensures turns are processed even if the main update thread has issues
 */

#include <cstdio>
#include <ctime>
#include <unistd.h>
#include <mysql/mysql.h>

int main() {
    printf("Turn Processor Helper Starting...\n");
    
    MYSQL *conn = mysql_init(NULL);
    if (conn == NULL) {
        fprintf(stderr, "mysql_init() failed\n");
        return 1;
    }
    
    // Get database credentials from environment
    const char* host = getenv("DB_HOST") ? getenv("DB_HOST") : "mysql";
    const char* user = getenv("DB_USER") ? getenv("DB_USER") : "archspace";
    const char* pass = getenv("DB_PASSWORD") ? getenv("DB_PASSWORD") : "archspace123";
    const char* db = getenv("DB_NAME") ? getenv("DB_NAME") : "Archspace2";
    
    if (mysql_real_connect(conn, host, user, pass, db, 3306, NULL, 0) == NULL) {
        fprintf(stderr, "mysql_real_connect() failed: %s\n", mysql_error(conn));
        mysql_close(conn);
        return 1;
    }
    
    printf("Connected to database successfully\n");
    
    int turn = 0;
    time_t last_turn_time = time(NULL);
    const int turn_interval = 300; // 5 minutes
    
    while (1) {
        time_t now = time(NULL);
        
        if (now - last_turn_time >= turn_interval) {
            turn++;
            printf("[%ld] Processing turn %d\n", now, turn);
            
            // Update all players' turns
            char query[512];
            snprintf(query, sizeof(query), 
                "UPDATE player SET turn = %d, tick = %ld WHERE game_id > 0 AND game_id != 9999999",
                turn, now + turn_interval);
            
            if (mysql_query(conn, query)) {
                fprintf(stderr, "UPDATE failed: %s\n", mysql_error(conn));
            } else {
                printf("Updated %llu players\n", mysql_affected_rows(conn));
            }
            
            // Simple resource update
            snprintf(query, sizeof(query),
                "UPDATE player SET production = production + 100, research = research + 10 WHERE game_id > 0 AND game_id != 9999999");
            mysql_query(conn, query);
            
            last_turn_time = now;
        }
        
        sleep(10); // Check every 10 seconds
    }
    
    mysql_close(conn);
    return 0;
}