<p align="center">
    <a href="https://gp247.net"><img src="https://static.gp247.net/logo/logo.png" height="100"></a>
    <a href="https://s-cart.org"><img src="https://s-cart.org/logo.png" height="100"><a/>
</p>
<p align="center">Hệ thống website thương mại điện tử miễn phí cho doanh nghiệp<br>
    <code><b>composer require GP247/Shop</b></code></p>

<p align="center">
<a href="https://packagist.org/packages/GP247/Shop"><img src="https://poser.pugx.org/GP247/Shop/d/total.svg" alt="Tổng số lượt tải"></a>
<a href="https://packagist.org/packages/GP247/Shop"><img src="https://poser.pugx.org/GP247/Shop/v/stable.svg" alt="Phiên bản ổn định mới nhất"></a>
<a href="https://packagist.org/packages/GP247/Shop"><img src="https://poser.pugx.org/GP247/Shop/license.svg" alt="Giấy phép"></a>
</p>


## Giới thiệu

GP247/Shop là một trong những gói chính của hệ sinh thái GP247, biến GP247 thành một cửa hàng trực tuyến mạnh mẽ cho doanh nghiệp. Gói này cung cấp đầy đủ chức năng thương mại điện tử trong khi kế thừa tất cả các tính năng của hệ sinh thái GP247.

## Tính năng chính

### Tính năng Thương mại điện tử
- Quản lý Sản phẩm
  - Danh mục và thuộc tính sản phẩm
  - Biến thể và tùy chọn sản phẩm
  - Quản lý kho
  - Nhập/xuất hàng loạt
- Quản lý Đơn hàng
  - Xử lý và theo dõi đơn hàng
  - Nhiều cổng thanh toán
  - Tích hợp phương thức vận chuyển
  - Quản lý trạng thái đơn hàng
- Quản lý Khách hàng
  - Hồ sơ và tài khoản khách hàng
  - Quản lý địa chỉ
  - Lịch sử đơn hàng
  - Nhóm khách hàng và giảm giá
- Công cụ Marketing
  - Khuyến mãi và giảm giá
  - Quản lý mã giảm giá
  - Tích hợp bản tin
  - Đánh giá và xếp hạng sản phẩm
- Tính năng Mua sắm
  - Giỏ hàng
  - Danh sách yêu thích
  - So sánh sản phẩm
  - Sản phẩm đã xem gần đây
- Hỗ trợ Đa người bán
  - Bảng điều khiển người bán
  - Quản lý hoa hồng
  - Quản lý sản phẩm người bán
  - Theo dõi đơn hàng người bán

### Tính năng Hệ sinh thái GP247
- Quản lý Nội dung Trang
- Hệ thống Mẫu Linh hoạt
- Hệ thống Plugin Mở rộng
- Quản lý Điều hướng & Liên kết
- Tích hợp Biểu mẫu Liên hệ & Đăng ký
- Hỗ trợ Đa ngôn ngữ
- Tối ưu hóa SEO
- Thiết kế Tương thích Di động
- Tính năng Bảo mật
- Sao lưu và Khôi phục

## Cài đặt

### Lựa chọn 1: Cho GP247 CMS hiện có
1. Đảm bảo đã cài đặt GP247 CMS (bao gồm Laravel, GP247/Core, GP247/Front)
2. Cài đặt GP247/Shop thông qua Composer:
```bash
composer require GP247/Shop
```

### Lựa chọn 2: Cho Dự án Mới
Bạn có thể sử dụng mã nguồn s-cart đầy đủ đã bao gồm tất cả các thành phần cần thiết:
```bash
composer create-project s-cart/s-cart
``` 

<img src="https://static.s-cart.org/guide/info/s-cart-content.jpg">
<img src="https://static.s-cart.org/guide/use/common/shop.jpg">
<img src="https://static.s-cart.org/guide/use/common/dashboard.jpg">

## Tùy chỉnh

### Tùy chỉnh giao diện Admin
Để tùy chỉnh giao diện admin, chạy lệnh sau:
```bash
php artisan vendor:publish --tag=gp247:view-shop-admin
```
Các view sẽ được lưu trữ tại `resources/views/vendor/gp247-shop-admin`

### Tùy chỉnh giao diện Front
Để tùy chỉnh và cập nhật giao diện front, chạy lệnh:
```bash
php artisan vendor:publish --tag=gp247:view-shop-admin
```
Các view sẽ được lưu trữ tại `app/GP247/Templates/Default`

Trường hợp bạn không sử dụng template `Default`, vui lòng copy thủ công các view từ `vendor/gp247/shop/Views/front` tới view mới.

## Tài liệu
- Để xem tài liệu đầy đủ về hệ thống GP247, truy cập [https://gp247.net](https://gp247.net)
- Để xem tài liệu chi tiết về các tính năng thương mại điện tử, truy cập [https://s-cart.org](https://s-cart.org)

## Giấy phép
GP247/Shop là phần mềm mã nguồn mở được cấp phép theo [giấy phép MIT](https://opensource.org/licenses/MIT). 