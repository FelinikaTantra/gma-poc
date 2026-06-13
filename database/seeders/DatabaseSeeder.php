<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Channel;
use App\Models\Customer;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\KnowledgeBase;
use App\Models\AiSetting;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 0. Tenant Setup
        $plan = \DB::table('plans')->insertGetId([
            'name' => 'Enterprise',
            'max_users' => 10,
            'max_channels' => 5,
            'max_ai_requests' => 10000,
            'created_at' => now()
        ]);

        $companyId = \DB::table('companies')->insertGetId([
            'name' => 'PT OmniChat Demo',
            'plan_id' => $plan,
            'created_at' => now()
        ]);

        $superAdminRole = \App\Models\Role::create([
            'name' => 'Super Admin',
            'permissions' => ['inbox', 'settings', 'users'],
            'company_id' => $companyId
        ]);

        $managerRole = \App\Models\Role::create([
            'name' => 'Manager',
            'permissions' => ['inbox', 'settings'],
            'company_id' => $companyId
        ]);

        $agentRole = \App\Models\Role::create([
            'name' => 'Agent / CS',
            'permissions' => ['inbox'],
            'company_id' => $companyId
        ]);

        $user = \App\Models\User::create([
            'name' => 'Super Admin',
            'email' => 'admin@omnichat.com',
            'password' => \Hash::make('password'),
            'company_id' => $companyId,
            'role_id' => $superAdminRole->id,
            'role' => 'super_admin'
        ]);

        // 1. Settings & Knowledge Base
        AiSetting::create(['full_control' => true]);
        
        KnowledgeBase::create([
            'company_id' => $companyId,
            'category' => 'FAQ',
            'title' => 'Apakah bisa COD?',
            'content' => 'Ya, tersedia COD area tertentu.'
        ]);
        KnowledgeBase::create([
            'company_id' => $companyId,
            'category' => 'SOP Customer Service',
            'title' => 'Jawaban tidak diketahui',
            'content' => 'Jika informasi tidak ditemukan, minta customer menunggu admin.'
        ]);

        // 2. Channels
        $telegram = Channel::create([
            'company_id' => $companyId,
            'name' => 'Telegram',
            'type' => 'telegram',
            'status' => 'Connected',
            'config_json' => ['bot_token' => '12345:ABC', 'username' => '@deposusu_bot']
        ]);

        // 3. Customer & Conversation
        $customer = Customer::create([
            'company_id' => $companyId,
            'name' => 'Budi Santoso',
            'username' => 'budis',
            'phone' => '081234567890',
            'channel_id' => $telegram->id,
            'external_id' => '123456789'
        ]);

        $conversation = Conversation::create([
            'company_id' => $companyId,
            'customer_id' => $customer->id,
            'channel_id' => $telegram->id,
            'status' => 'waiting_admin',
            'unread_count' => 1,
            'last_message_at' => now()
        ]);

        // 4. Messages (History)
        Message::create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'customer',
            'message_type' => 'text',
            'message' => 'Halo min, mau tanya barang ini bisa dikirim hari ini pakai Gojek?',
            'created_at' => now()->subMinutes(10)
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'ai',
            'message_type' => 'text',
            'message' => 'Halo! Bisa, kami menyediakan pengiriman instan. Mau dikirim ke area mana kak?',
            'created_at' => now()->subMinutes(8)
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'customer',
            'message_type' => 'text',
            'message' => 'Ke Jakarta Selatan kak. Ongkirnya berapa ya?',
            'created_at' => now()->subMinutes(1)
        ]);
    }
}
