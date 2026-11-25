<x-layout title="Meal card preview" :admin="true">
    <header class="page-header"><a class="text-link" href="{{ route('admin.catalog.edit', ['meals', $meal->id]) }}">Back to meal</a><h1>Meal card preview</h1></header>
    <x-meal-list :meals="collect([$meal])" :preview="true"/>
</x-layout>
