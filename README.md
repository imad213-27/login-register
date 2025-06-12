# 👋 مرحبًا بك في مشروع إدارة المستخدمين

أهلاً بك في هذا المشروع البسيط الذي يهدف إلى إدارة المستخدمين من خلال قاعدة بيانات SQL.  
يمكنك استخدام هذا المشروع كنقطة انطلاق لأي تطبيق يتطلب تسجيل مستخدمين وتخزين معلوماتهم.

---

## 📦 مميزات المشروع

- إنشاء وتخزين المستخدمين في قاعدة بيانات.
- بنية قاعدة بيانات بسيطة وسهلة التعديل.
- إمكانية التوسعة لاحقًا لتشمل تسجيل الدخول، التحكم في الصلاحيات، وغيرها.

---

## 🗂️ جدول `users`

تم إعداد جدول باسم `users` لتخزين بيانات المستخدمين الأساسية.  
يمكنك إنشاء الجدول باستخدام الأمر التالي في قاعدة البيانات:

```sql
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    verification_code VARCHAR(10),
    code_expiry DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
