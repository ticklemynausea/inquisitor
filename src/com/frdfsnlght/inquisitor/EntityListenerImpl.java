/*
 * Copyright 2012 frdfsnlght <frdfsnlght@gmail.com>.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
package com.frdfsnlght.inquisitor;

import java.util.Date;
import org.bukkit.Material;
import org.bukkit.entity.Entity;
import org.bukkit.entity.Player;
import org.bukkit.entity.Projectile;
import org.bukkit.event.EventHandler;
import org.bukkit.event.EventPriority;
import org.bukkit.event.Listener;
import org.bukkit.event.entity.EntityDamageByEntityEvent;
import org.bukkit.event.entity.EntityDamageEvent;
import org.bukkit.event.entity.EntityDeathEvent;
import org.bukkit.event.entity.EntityShootBowEvent;
import org.bukkit.event.entity.EntityTameEvent;
import org.bukkit.event.entity.FoodLevelChangeEvent;
import org.bukkit.inventory.ItemStack;
import org.bukkit.projectiles.ProjectileSource;

/**
 *
 * @author Thomas Bennedum <frdfsnlght@gmail.com>
 */
public final class EntityListenerImpl implements Listener {

    @EventHandler(priority = EventPriority.MONITOR, ignoreCancelled = true)
    public void onEntityDamage(EntityDamageEvent event) {
        if (! (event.getEntity() instanceof Player)) return;
        Statistics stats = PlayerStats.group.getStatistics(((Player)event.getEntity()).getName());
        stats.add("travelDistances", "Falling", event.getEntity().getFallDistance());
    }

    @EventHandler(priority = EventPriority.MONITOR)
    public void onEntityDeath(EntityDeathEvent event) {
        Entity deadEnt = event.getEntity();
        if (! (deadEnt.getLastDamageCause() instanceof EntityDamageByEntityEvent)) return;

        EntityDamageByEntityEvent killEvent = (EntityDamageByEntityEvent) deadEnt.getLastDamageCause();
        Entity killerEnt = killEvent.getDamager();

        if (killerEnt instanceof Projectile) {
            ProjectileSource killerSource = ((Projectile)killerEnt).getShooter();
            if (!(killerSource instanceof Entity)) return;

            killerEnt = (Entity) killerSource;
        }

        if (! (killerEnt instanceof Player)) return;

        ItemStack inHand = ((Player)killerEnt).getItemInHand();
        Material weapon = null;
        if (inHand != null)
            weapon = inHand.getType();

        String killerName = ((Player)killerEnt).getName();
        Statistics stats = PlayerStats.group.getStatistics(killerName);
        String weaponName = ((weapon == null) || (weapon == Material.AIR)) ? "None" : Utils.titleCase(weapon.toString());

        if (deadEnt instanceof Player) {
            String deadName = ((Player)deadEnt).getName();
            Utils.debug("onPlayerKill '%s'", killerName);
            stats.incr("totalPlayersKilled");
            stats.set("lastPlayerKill", new Date());
            stats.set("lastPlayerKilled", deadName);
            stats.incr("playersKilled", deadName);
            stats.incr("playersKilledByWeapon", weaponName);
        } else {
            String deadName = Utils.normalizeEntityTypeName(deadEnt.getType());
            Utils.debug("onMobKill '%s'", killerName);
            stats.incr("totalMobsKilled");
            stats.set("lastMobKill", new Date());
            stats.set("lastMobKilled", deadName);
            stats.incr("mobsKilled", deadName);
            stats.incr("mobsKilledByWeapon", weaponName);
        }
    }

    @EventHandler(priority = EventPriority.MONITOR, ignoreCancelled = true)
    public void onEntityShootBow(EntityShootBowEvent event) {
        if (! (event.getEntity() instanceof Player)) return;
        PlayerStats.group.getStatistics(((Player)event.getEntity()).getName()).incr("arrowsShot");
    }

    @EventHandler(priority = EventPriority.MONITOR, ignoreCancelled = true)
    public void onEntityTame(EntityTameEvent event) {
        if (! (event.getOwner() instanceof Player)) return;
        PlayerStats.group.getStatistics(((Player)event.getOwner()).getName()).incr("animalsTamed", Utils.normalizeEntityTypeName(event.getEntityType()));
    }

    @EventHandler(priority = EventPriority.MONITOR, ignoreCancelled = true)
    public void onFoodLevelChange(FoodLevelChangeEvent event) {
        if (! (event.getEntity() instanceof Player)) return;
        Player player = (Player)event.getEntity();
        if (event.getFoodLevel() <= player.getFoodLevel()) return;
        Material food = player.getItemInHand().getType();
        if (! food.isEdible()) return;
        PlayerStats.group.getStatistics(player.getName()).incr("foodEaten", Utils.titleCase(food.name()));
    }

}
