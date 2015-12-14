# Update 2015 12 14
Project Abandoned  
Replace with https://github.com/ijortengab/ibank  
Repository tetap exists untuk kenangan.


Deskripsi
---------

ID-Internet-Banking menyediakan pustaka (*library*) untuk memudahkan dalam 
mendapatkan informasi saldo dan mutasi rekening dari bank yang memiliki
*internet banking*. Pustaka dibuat dalam bahasa PHP. 

### Latar Belakang

Bank di Indonesia yang memiliki *internet banking* tidak menyediakan *API* 
untuk membaca mutasi rekening dan saldo.

### Sasaran

*PHP Class* ini ditujukan bagi web programmer di tanah air tercinta.

### Cara kerja

Cara kerja program ini seperti *bot* atau *web crawler*. Program membaca halaman
html, mengisi *form login*, mencari dan menelusuri *link*, kemudian menyimpan
hasil mutasi dan saldo.

### Kelemahan/Keterbatasan

Program ini sangat bergantung kepada struktur html dari website bank yang 
bersangkutan. Jika struktur html berubah, maka kemungkinan besar program ini
akan tidak bekerja, sehingga program ini perlu di-*update* untuk bisa kembali
berfungsi.

Program ini tidak 100% *automatic*, beberapa bank menyediakan *captcha*. 
Program ini di-*design* untuk menjawab *captcha* secara manual, kemudian proses
selanjutnya bisa dijalankan secara *automatic*.

### Requirements

PHP 5.4

### Versi

0.0.2


Daftar Bank
-----------

Daftar bank yang sudah didukung oleh program ini.

### BNI

BNI menyediakan *internet banking* bagi nasabahnya dalam dua format utama, yakni 
*Personal* dan *Corporate*. Progam ini hanya mendukung pembacaan saldo dan 
mutasi rekening bagi pengguna format *Personal*.

BNI Personal terdapat dua *layout* yakni web *standard* dan *mobile*. 
Program ini menggunakan  format *mobile* yang tidak terdapat *captcha*. 

Kontak
------
Hubungi author jika ada sesuatu yang ingin ditanyakan atau *request feature*. 
E-mail di em underscore er o je i dua delapan @yahoo.com.
