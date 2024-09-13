# Cài đặt project
# Yêu cầu

- Php 7.4
- Composer  

### Bước 1: Cài đặt composer cho project.

```bash 
composer install
```

### Bước 2: Cấp quyền docker cho phép đọc log và thông tin container cho project 

**Lưu ý:** 
- Với Ubuntu Nginx có user mặc định là www-data 
``` bash
    sudo usermod -aG docker www-data
```

- Với CentOS Nginx có user mặc định là nginx 

``` bash
    sudo usermod -aG docker nginx
```
### Bước 3: Cấp quyền systemd-journal đọc log hệ thống cho project 

- Với Ubuntu Nginx có user mặc định là www-data 
``` bash
    sudo usermod -aG systemd-journal www-data
```

- Với CentOS Nginx có user mặc định là nginx 

``` bash
    sudo usermod -aG systemd-journal nginx
```
### Bước 4: Thêm cấu hình .env cho project 
- TOKEN_API_SERVER: phần cấu hình token để cho server master có thể gọi đến để lấy thông tin.

