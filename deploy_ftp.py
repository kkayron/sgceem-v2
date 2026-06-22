import ftplib
import os

FTP_HOST = "ftpupload.net"
FTP_USER = "if0_42243179"
FTP_PASS = "mPRkpYEduOM"

def upload_dir(ftp, local_dir, remote_dir):
    try:
        ftp.cwd(remote_dir)
    except ftplib.error_perm:
        try:
            ftp.mkd(remote_dir)
            ftp.cwd(remote_dir)
        except Exception as e:
            print(f"Failed to create/cwd to {remote_dir}: {e}")
            return

    for item in os.listdir(local_dir):
        local_path = os.path.join(local_dir, item)
        if os.path.isfile(local_path):
            print(f"Uploading {local_path} to {item}...")
            with open(local_path, 'rb') as f:
                ftp.storbinary(f"STOR {item}", f)
        elif os.path.isdir(local_path):
            upload_dir(ftp, local_path, item)
            ftp.cwd("..")

try:
    print("Connecting to FTP...")
    ftp = ftplib.FTP(FTP_HOST)
    ftp.login(FTP_USER, FTP_PASS)
    print("Logged in successfully.")
    
    upload_dir(ftp, "backend", "htdocs")
    
    # Upload db dump as well just in case we need it
    with open("sgceem_v2_clean.sql", 'rb') as f:
        ftp.cwd("/")
        ftp.storbinary("STOR sgceem_v2_clean.sql", f)
        
    ftp.quit()
    print("Upload Complete!")
except Exception as e:
    print(f"Error: {e}")
