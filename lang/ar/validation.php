<?php

return [
    'required' => 'حقل :attribute مطلوب.',
    'string' => 'يجب أن يكون حقل :attribute نصاً.',
    'enum' => 'يرجى اختيار العربية أو الإنجليزية.',
    'email' => 'يجب أن يكون حقل :attribute بريداً إلكترونياً صالحاً.',
    'unique' => 'قيمة :attribute مستخدمة بالفعل.',
    'regex' => 'صيغة :attribute غير صحيحة.',
    'confirmed' => 'تأكيد :attribute غير مطابق.',
    'boolean' => 'قيمة :attribute غير صحيحة.',
    'digits' => 'يجب أن يتكون :attribute من :digits أرقام.',
    'min' => ['string' => 'يجب ألا يقل :attribute عن :min أحرف.'],
    'max' => ['string' => 'يجب ألا يتجاوز :attribute عدد :max أحرف.'],
    'password' => ['letters' => 'يجب أن تتضمن :attribute حرفاً.', 'numbers' => 'يجب أن تتضمن :attribute رقماً.'],
    'attributes' => ['locale' => 'لغة العرض', 'name' => 'الاسم الكامل', 'username' => 'اسم المستخدم', 'email' => 'البريد الإلكتروني', 'phone' => 'رقم الجوال', 'password' => 'كلمة المرور', 'code' => 'رمز التأكيد', 'login' => 'البريد الإلكتروني أو اسم المستخدم'],
];
